<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Webklex\IMAP\Facades\Client;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;

class EmailService
{
    protected $client;
    protected $cachePrefix = 'emails:';
    protected $cacheTTL = 3600; // 1 hour

    public function __construct()
    {
        $this->client = Client::account('default');
        $this->client->connect();
    }

    /**
     * Fetch semua email dengan cache support
     */
    public function fetchAllEmails($forceRefresh = false): array
    {
        $cacheKey = $this->cachePrefix . 'all_folders';

        // Jika tidak force refresh, cek cache dulu
        if (!$forceRefresh) {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                Log::info('Email data retrieved from cache');
                return json_decode($cached, true);
            }
        }

        Log::info('Fetching emails from IMAP server');
        $result = [];

        foreach ($this->folderMap as $key => $imapFolderName) {
            try {
                $folder = $this->client->getFolder($imapFolderName);
                $messages = $folder->messages()->all()->limit(50)->get();

                $emails = $messages->map(fn($msg) => $this->parseEmail($msg))->toArray();

                // Urutkan manual by timestamp desc
                usort($emails, function ($a, $b) {
                    $timeA = $a['timestamp'] ? strtotime($a['timestamp']) : 0;
                    $timeB = $b['timestamp'] ? strtotime($b['timestamp']) : 0;
                    return $timeB <=> $timeA;
                });

                $result[$key] = $emails;

                // Cache per folder juga
                $folderCacheKey = $this->cachePrefix . 'folder:' . $key;
                Redis::setex($folderCacheKey, $this->cacheTTL, json_encode($emails));
            } catch (\Exception $e) {
                Log::error("Failed to fetch folder {$key}: " . $e->getMessage());
                $result[$key] = [];
            }
        }

        // Cache semua data
        Redis::setex($cacheKey, $this->cacheTTL, json_encode($result));
        Log::info('Email data cached successfully');

        return $result;
    }

    public function moveEmailByMessageId($folder, $messageIds, $targetFolder)
    {
        $client = $this->client;
        $client->connect();

        $imapSource = $this->resolveFolder($folder);
        $imapTarget = $this->resolveFolder($targetFolder);

        $mailbox = $client->getFolder($imapSource);
        $messageIds = is_array($messageIds) ? $messageIds : [$messageIds];

        $moved = [];
        foreach ($messageIds as $messageId) {
            $message = $this->findMessageByMessageId($messageId, $imapSource);
            if ($message) {
                // Ambil data email sebelum dipindah untuk update cache
                $emailData = $this->parseEmail($message);

                $message->move($imapTarget);
                $moved[] = $messageId;

                // Update cache pakai messageId
                $this->updateCacheAfterMoveByMessageId($emailData, $folder, $targetFolder, $messageId);
            }
        }

        return $moved;
    }

    public function deletePermanentAll()
    {
        try {
            $imapFolder = $this->resolveFolder('deleted');
            $folder = $this->client->getFolder($imapFolder);

            if (!$folder) {
                throw new \Exception("Folder {$imapFolder} tidak ditemukan");
            }

            $messages = $folder->messages()->all()->get();

            foreach ($messages as $message) {
                $message->delete(true);
            }

            // Clear cache untuk folder deleted
            $this->clearFolderCache('deleted');

            return [
                'success' => true,
                'message' => 'All emails in Deleted Items permanently deleted'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to permanently delete emails: ' . $e->getMessage()
            ];
        }
    }

    public function deletePermanent($messageIds)
    {
        $this->client->connect();

        $imapFolder = $this->resolveFolder('deleted');
        $folder = $this->client->getFolder($imapFolder);

        if (!$folder) {
            throw new \Exception("Folder Deleted Items tidak ditemukan");
        }

        $messageIds = is_array($messageIds) ? $messageIds : [$messageIds];
        $deleted = [];

        foreach ($messageIds as $messageId) {
            $message = $this->findMessageByMessageId($messageId, $imapFolder);

            if ($message) {
                // Hapus permanen (expunge = true)
                $message->delete(true);
                $deleted[] = $messageId;

                // Update cache (hapus dari deleted folder cache)
                $this->removeEmailFromCache('deleted', $messageId);
            }
        }

        return $deleted;
    }


    public function markAsRead($folder, $messageId)
    {
        $this->client->connect();
        $imapFolder = $this->resolveFolder($folder);

        $message = $this->findMessageByMessageId($messageId, $imapFolder);

        if (!$message) {
            throw new \Exception("Email dengan Message-ID {$messageId} tidak ditemukan di {$imapFolder}");
        }

        try {
            $message->setFlag('Seen');

            // Update cache pakai messageId
            $this->updateEmailFlagInCacheByMessageId($folder, $messageId, 'seen', true);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mark as read: " . $e->getMessage());
        }
    }

    public function markAsFlagged($folder, $messageId)
    {
        $this->client->connect();
        $imapFolder = $this->resolveFolder($folder);

        $message = $this->findMessageByMessageId($messageId, $imapFolder);

        if (!$message) {
            throw new \Exception("Email dengan Message-ID {$messageId} tidak ditemukan di {$imapFolder}");
        }

        try {
            $message->setFlag('Flagged');

            $this->updateEmailFlagInCacheByMessageId($folder, $messageId, 'flagged', true);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mark as flagged: " . $e->getMessage());
        }
    }

    public function markAsUnflagged($folder, $messageId)
    {
        $this->client->connect();
        $imapFolder = $this->resolveFolder($folder);

        $message = $this->findMessageByMessageId($messageId, $imapFolder);

        if (!$message) {
            throw new \Exception("Email dengan Message-ID {$messageId} tidak ditemukan di {$imapFolder}");
        }

        try {
            $message->unsetFlag('Flagged');

            $this->updateEmailFlagInCacheByMessageId($folder, $messageId, 'flagged', false);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mark as unflagged: " . $e->getMessage());
        }
    }

    public function downloadAttachment($uid, $filename)
    {
        try {
            $message = $this->findMessageByUid($uid);

            if (!$message) {
                throw new \Exception("Email with UID {$uid} not found");
            }

            $attachments = $message->getAttachments();

            foreach ($attachments as $attachment) {
                if ($attachment->name === $filename) {
                    return [
                        'content' => $attachment->getContent(),
                        'filename' => $attachment->name,
                        'mime_type' => $attachment->mime,
                        'size' => $attachment->size
                    ];
                }
            }

            throw new \Exception("Attachment '{$filename}' not found in email");
        } catch (\Exception $e) {
            Log::error("Download attachment failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveDraft($to, $subject, $body, $attachments = [])
    {
        $this->client->connect();

        $imapFolder = $this->resolveFolder('draft');
        $folder = $this->client->getFolder($imapFolder);

        if (!$folder) {
            throw new \Exception("Drafts folder not found in IMAP");
        }

        $email = new SymfonyEmail();
        $email->subject($subject ?? "(no subject)");

        if ($to) {
            $email->to(new Address($to));
        }

        $email->from(new Address("magang@rekaprihatanto.web.id", "Draft"));

        $plainText = strip_tags($body ?? "");
        $email->text($plainText);
        $email->html($body ?? "");

        foreach ($attachments as $file) {
            $email->attachFromPath(
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $file->getMimeType()
            );
        }

        $raw = $email->toString();
        $folder->appendMessage($raw, ["\\Draft"]);

        // Clear cache untuk folder draft karena ada email baru
        $this->clearFolderCache('draft');

        return [
            "subject"     => $subject,
            "to"          => $to,
            "sender"      => "Draft",
            "senderEmail" => "magang@rekaprihatanto.web.id",
            "body"        => [
                "text" => $plainText,
                "html" => $body,
            ],
            "attachments" => collect($attachments)->map(fn($f) => $f->getClientOriginalName())->toArray(),
        ];
    }

    /**
     * Cache Helper Methods
     */
    private function updateCacheAfterMoveByMessageId($emailData, $sourceFolder, $targetFolder, $messageId)
    {
        // Remove from source folder cache
        $sourceCacheKey = $this->cachePrefix . 'folder:' . $sourceFolder;
        $sourceCached = Redis::get($sourceCacheKey);

        if ($sourceCached) {
            $sourceEmails = json_decode($sourceCached, true);
            $sourceEmails = array_filter($sourceEmails, function ($email) use ($messageId) {
                return $email['messageId'] != $messageId;
            });
            Redis::setex($sourceCacheKey, $this->cacheTTL, json_encode(array_values($sourceEmails)));
        }

        // Add to target folder cache
        $targetCacheKey = $this->cachePrefix . 'folder:' . $targetFolder;
        $targetCached = Redis::get($targetCacheKey);

        if ($targetCached) {
            $targetEmails = json_decode($targetCached, true);

            // Update folder path in email data
            $emailData['folder'] = $this->resolveFolder($targetFolder);

            array_unshift($targetEmails, $emailData);

            Redis::setex($targetCacheKey, $this->cacheTTL, json_encode($targetEmails));
        }

        // Clear all_folders cache
        Redis::del($this->cachePrefix . 'all_folders');
    }

    private function updateEmailFlagInCacheByMessageId($folder, $messageId, $flagName, $flagValue)
    {
        $cacheKey = $this->cachePrefix . 'folder:' . $folder;
        $cached = Redis::get($cacheKey);

        if ($cached) {
            $emails = json_decode($cached, true);

            foreach ($emails as &$email) {
                if (isset($email['messageId']) && $email['messageId'] == $messageId) {
                    $email[$flagName] = $flagValue;
                    break;
                }
            }

            Redis::setex($cacheKey, $this->cacheTTL, json_encode($emails));
        }

        Redis::del($this->cachePrefix . 'all_folders');
    }

    private function clearFolderCache($folder)
    {
        $cacheKey = $this->cachePrefix . 'folder:' . $folder;
        Redis::del($cacheKey);
        Redis::del($this->cachePrefix . 'all_folders');
    }

    private function removeEmailFromCache($folder, $messageId)
    {
        $cacheKey = $this->cachePrefix . 'folder:' . $folder;
        $cached = Redis::get($cacheKey);

        if ($cached) {
            $emails = json_decode($cached, true);
            $emails = array_filter($emails, function ($email) use ($messageId) {
                return $email['messageId'] !== $messageId;
            });

            Redis::setex($cacheKey, $this->cacheTTL, json_encode(array_values($emails)));
        }

        // Clear all_folders cache juga
        Redis::del($this->cachePrefix . 'all_folders');
    }


    /**
     * Existing methods (tidak berubah)
     */
    private function findMessageByUid($uid)
    {
        $folders = ['INBOX', 'Sent Items', 'Drafts', 'Deleted Items', 'Junk Mail', 'Archive'];

        foreach ($folders as $folderName) {
            try {
                $folder = $this->client->getFolder($folderName);
                $message = $folder->query()->getMessageByUid($uid);

                if ($message) {
                    return $message;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            $allFolders = $this->client->getFolders();
            foreach ($allFolders as $folder) {
                try {
                    $message = $folder->query()->getMessageByUid($uid);
                    if ($message) {
                        return $message;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            // Ignore error
        }

        return null;
    }

    private function findMessageByMessageId($messageId, $folder)
    {
        try {
            $mailbox = $this->client->getFolder($folder);
            $messages = $mailbox->query()->since(now()->subYears(5))->get(); // ambil semua message (bisa optimisasi filter)

            foreach ($messages as $msg) {
                $msgId = $msg->getMessageId() ? (string) $msg->getMessageId()->first() : null;
                if ($msgId && $msgId === $messageId) {
                    return $msg;
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }


    private function parseEmail($message)
    {
        $dateAttr = $message->getDate()->first() ?? $message->getInternalDate()->first();

        $flagsRaw = $message->getFlags()->toArray();
        $flags = [
            'seen'     => in_array('Seen', $flagsRaw),
            'answered' => in_array('Answered', $flagsRaw),
            'flagged'  => in_array('Flagged', $flagsRaw),
        ];

        $mapRecipients = function ($recipients) {
            $list = [];
            foreach ($recipients->all() as $r) {
                $list[] = [
                    'email' => $r->mail ?? null
                ];
            }
            return $list;
        };

        $toList = $mapRecipients($message->getTo());

        $attachmentsList = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachmentsList[] = [
                'filename'      => $attachment->name,
                'size'          => $attachment->size,
                'download_url'  => url("emails/attachments/{$message->getUid()}/download/" . urlencode($attachment->name)),
            ];
        }

        return [
            'uid'           => $message->getUid(),
            'folder'        => $message->getFolderPath(),
            'messageId'     => $message->getMessageId() ? (string) $message->getMessageId()->first() : null,
            'sender'        => $message->getFrom()[0]->personal ?? $message->getFrom()[0]->mail,
            'senderEmail'   => $message->getFrom()[0]->mail ?? null,
            'subject'       => (string) $message->getSubject(),
            'preview'       => \Illuminate\Support\Str::limit(strip_tags($message->getTextBody() ?? $message->getHTMLBody()), 120),
            'timestamp'     => $dateAttr ? $dateAttr->format('d F Y, H:i T') : null,
            'seen'          => $flags['seen'],
            'flagged'       => $flags['flagged'],
            'answered'      => $flags['answered'],
            'recipients'    => $toList,
            'body'          => [
                'text' => $message->getTextBody(),
                'html' => $message->getHTMLBody(),
            ],
            'rawAttachments' => $attachmentsList,
        ];
    }

    private $folderMap = [
        'inbox'   => 'INBOX',
        'sent'    => 'Sent Items',
        'draft'   => 'Drafts',
        'deleted' => 'Deleted Items',
        'junk'    => 'Junk Mail',
        'archive' => 'Archive',
    ];

    public function resolveFolder($key)
    {
        return $this->folderMap[strtolower($key)] ?? 'INBOX';
    }
}
