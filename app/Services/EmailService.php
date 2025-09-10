<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class EmailService
{
    protected $client;

    public function __construct()
    {
        $this->client = Client::account('default');
        $this->client->connect();
    }

    /**
     * Sekali fetch semua folder
     */
    public function fetchAllEmails(): array
    {
        $result = [];

        foreach ($this->folderMap as $key => $imapFolderName) {
            try {
                $folder = $this->client->getFolder($imapFolderName);

                $messages = $folder->messages()->all()->limit(10)->get();

                $emails = $messages->map(fn($msg) => $this->parseEmail($msg))->toArray();

                // urutkan manual by timestamp desc
                usort($emails, function ($a, $b) {
                    $timeA = $a['timestamp'] ? strtotime($a['timestamp']) : 0;
                    $timeB = $b['timestamp'] ? strtotime($b['timestamp']) : 0;
                    return $timeB <=> $timeA;
                });

                $result[$key] = $emails;
            } catch (\Exception $e) {
                $result[$key] = [];
            }
        }

        return $result;
    }


    public function moveEmail($folder, $uids, $targetFolder)
    {
        $client = $this->client;
        $client->connect();

        $imapSource = $this->resolveFolder($folder);
        $imapTarget = $this->resolveFolder($targetFolder);

        $mailbox = $client->getFolder($imapSource);

        $uids = is_array($uids) ? $uids : [$uids]; // pastikan selalu array

        $moved = [];
        foreach ($uids as $uid) {
            $message = $mailbox->query()->getMessageByUid($uid);
            if ($message) {
                $message->move($imapTarget);
                $moved[] = $uid;
            }
        }

        return $moved;
    }



    // Create a new folder
    public function createFolder($folderName)
    {
        $this->client->connect();

        try {
            // pilih folder default supaya "selected mailbox" valid
            $inbox = $this->client->getFolder('INBOX');
            $inbox->select();

            // baru buat folder baru
            $this->client->createFolder($folderName);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal membuat folder {$folderName}: " . $e->getMessage());
        }
    }

    // List all folders
    public function listFolders()
    {
        $this->client->connect();
        $folders = $this->client->getFolders(true); // true = recursive

        $result = [];
        foreach ($folders as $folder) {
            $result[] = $folder->path;
        }

        return $result;
    }

    // Mark email as read
    public function markAsRead($folder, $uid)
    {
        $this->client->connect();

        // Gunakan resolver agar frontend bisa kirim "inbox" atau "deleted"
        $imapFolder = $this->resolveFolder($folder);

        $mailbox = $this->client->getFolder($imapFolder);

        if (!$mailbox) {
            throw new \Exception("Folder {$imapFolder} tidak ditemukan di server IMAP");
        }

        $message = $mailbox->query()->getMessageByUid($uid);

        if (!$message) {
            throw new \Exception("Email dengan UID {$uid} tidak ditemukan di {$imapFolder}");
        }

        try {
            $message->setFlag('Seen'); // tandai sebagai sudah dibaca
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mark as read: " . $e->getMessage());
        }
    }

    // Mark email as flagged
    public function markAsFlagged($folder, $uid)
    {
        $this->client->connect();

        // gunakan resolver
        $imapFolder = $this->resolveFolder($folder);

        $mailbox = $this->client->getFolder($imapFolder);

        if (!$mailbox) {
            throw new \Exception("Folder {$imapFolder} tidak ditemukan di server IMAP");
        }

        $message = $mailbox->query()->getMessageByUid($uid);

        if (!$message) {
            throw new \Exception("Email dengan UID {$uid} tidak ditemukan di {$imapFolder}");
        }

        try {
            $message->setFlag('Flagged');
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mark as flagged: " . $e->getMessage());
        }
    }

    public function markAsUnflagged($folder, $uid)
    {
        $this->client->connect();

        // gunakan resolver
        $imapFolder = $this->resolveFolder($folder);

        $mailbox = $this->client->getFolder($imapFolder);

        if (!$mailbox) {
            throw new \Exception("Folder {$imapFolder} tidak ditemukan di server IMAP");
        }

        $message = $mailbox->query()->getMessageByUid($uid);

        if (!$message) {
            throw new \Exception("Email dengan UID {$uid} tidak ditemukan di {$imapFolder}");
        }

        try {
            $message->unsetFlag('Flagged');
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mark as unflagged: " . $e->getMessage());
        }
    }

    /**
     * Download attachment berdasarkan UID email dan nama file
     */
    public function downloadAttachment($uid, $filename)
    {
        try {
            // Cari message berdasarkan UID di semua folder
            $message = $this->findMessageByUid($uid);

            if (!$message) {
                throw new \Exception("Email with UID {$uid} not found");
            }

            // Cari attachment berdasarkan filename
            $attachments = $message->getAttachments();

            foreach ($attachments as $attachment) {
                if ($attachment->name === $filename) {
                    // Return attachment data untuk di-download
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


    /**
     * Cari message berdasarkan UID di semua folder
     */
    private function findMessageByUid($uid)
    {
        // Coba cari di folder-folder utama
        $folders = ['INBOX', 'Sent Items', 'Drafts', 'Deleted Items', 'Junk Mail', 'Archive'];

        foreach ($folders as $folderName) {
            try {
                $folder = $this->client->getFolder($folderName);
                $message = $folder->query()->getMessageByUid($uid);

                if ($message) {
                    return $message;
                }
            } catch (\Exception $e) {
                // Continue to next folder
                continue;
            }
        }

        // Jika tidak ditemukan di folder utama, coba di semua folder
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


    /**
     * Parsing email
     */
    private function parseEmail($message)
    {
        // Ambil tanggal
        $dateAttr = $message->getDate()->first() ?? $message->getInternalDate()->first();

        // Flags
        $flagsRaw = $message->getFlags()->toArray();
        $flags = [
            'seen'     => in_array('Seen', $flagsRaw),
            'answered' => in_array('Answered', $flagsRaw),
            'flagged'  => in_array('Flagged', $flagsRaw),
        ];

        // Recipients
        $mapRecipients = function ($recipients) {
            $list = [];
            foreach ($recipients->all() as $r) {
                $list[] = [
                    'email' => $r->mail ?? null
                ];
            }
            return $list;
        };

        $toList  = $mapRecipients($message->getTo());

        // Attachments
        $attachmentsList = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachmentsList[] = [
                'filename'      => $attachment->name,
                'size'          => $attachment->size,
                'download_url'  => url("emails/attachments/{$message->getUid()}/download/" . urlencode($attachment->name)),
            ];
        }

        // === Bentuk JSON sesuai kebutuhan frontend ===
        return [
            'uid'           => $message->getUid(),
            'folder'        => $message->getFolderPath(),
            'messageId'  => $message->getMessageId() ? (string) $message->getMessageId()->first() : null,
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
