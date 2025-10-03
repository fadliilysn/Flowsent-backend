<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;

class MailerService
{
    protected $cachePrefix = 'emails:';
    protected $cacheTTL = 3600; // 1 jam

    public function sendEmail($to, $subject, $body, $attachments = [], $draftId = null, $storedAttachments = [])
    {
        try {
            Log::info('Starting sendEmail', [
                'to' => $to,
                'subject' => $subject,
                'draftId' => $draftId,
                'new_attachments_count' => count($attachments),
                'stored_attachments_count' => count($storedAttachments),
            ]);

            // Kumpulkan semua attachment (new + stored jika dari draft)
            $allAttachments = [];

            // Attachments baru dari upload
            if (!empty($attachments)) {
                foreach ($attachments as $file) {
                    if ($file && $file->isValid()) {
                        $allAttachments[] = [
                            'path' => $file->getRealPath(),
                            'name' => $file->getClientOriginalName(),
                            'mime' => $file->getMimeType() ?? 'application/octet-stream',
                            'size' => $file->getSize(),
                        ];
                        Log::info('New attachment added', [
                            'name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'mime' => $file->getMimeType(),
                        ]);
                    } else {
                        Log::warning('Invalid new attachment found', ['file' => $file]);
                    }
                }
            }

            // Attachments lama dari storage jika draft
            if ($draftId && !empty($storedAttachments)) {
                foreach ($storedAttachments as $att) {
                    $storedName = preg_replace('/\s+/', '_', $att['name'] ?? $att['original_name']);
                    $path = "attachments/drafts/{$draftId}/{$storedName}";
                    if (Storage::exists($path)) {
                        $allAttachments[] = [
                            'path' => Storage::path($path),
                            'name' => $att['name'] ?? $att['original_name'],
                            'mime' => $att['mime'] ?? $att['mime_type'] ?? 'application/octet-stream',
                            'size' => Storage::size($path),
                        ];
                        Log::info('Stored attachment added', [
                            'path' => $path,
                            'name' => $att['name'] ?? $att['original_name'],
                            'mime' => $att['mime'] ?? $att['mime_type'],
                            'size' => Storage::size($path),
                        ]);
                    } else {
                        Log::error('Stored attachment not found', ['path' => $path]);
                    }
                }
            }

            // Kirim email menggunakan Laravel Mail
            Mail::send([], [], function ($message) use ($to, $subject, $body, $allAttachments) {
                $message->to($to)
                    ->subject($subject)
                    ->html($body);

                foreach ($allAttachments as $att) {
                    if (file_exists($att['path'])) {
                        $message->attach($att['path'], [
                            'as' => $att['name'],
                            'mime' => $att['mime'],
                        ]);
                        Log::info('Attachment added to email', [
                            'name' => $att['name'],
                            'path' => $att['path'],
                            'mime' => $att['mime'],
                        ]);
                    } else {
                        Log::error('Attachment file not found', ['path' => $att['path']]);
                    }
                }
            });

            // Simpan email ke folder Sent + update cache
            $emailData = $this->saveToSent($to, $subject, $body, $allAttachments);

            // Update cache folder "sent"
            if ($emailData) {
                $this->updateSentCache($emailData);
            }

            // Jika dari draft, hapus storage folder
            if ($draftId) {
                if (Storage::exists("attachments/drafts/{$draftId}")) {
                    Storage::deleteDirectory("attachments/drafts/{$draftId}");
                    Log::info("Draft storage folder deleted: attachments/drafts/{$draftId}");
                } else {
                    Log::warning("Draft storage folder not found: attachments/drafts/{$draftId}");
                }
            }

            Log::info('Email sent successfully', ['to' => $to, 'subject' => $subject]);
            return true;
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'to' => $to,
                'subject' => $subject,
                'draftId' => $draftId,
            ]);
            throw new \Exception("Error sending email: " . $e->getMessage());
        }
    }

    private function saveToSent($to, $subject, $body, $allAttachments = [])
    {
        try {
            $client = Client::account('default');
            $client->connect();

            $sentFolder = $client->getFolder('Sent Items');
            if (!$sentFolder) {
                Log::warning("Tidak ditemukan folder Sent di akun IMAP.");
                return null;
            }

            $email = new SymfonyEmail();
            $email->from(new Address(config('mail.from.address'), "Me"))
                ->to(new Address($to))
                ->subject($subject ?? "(no subject)")
                ->text(strip_tags($body ?? ""))
                ->html($body ?? "");

            $attachmentsList = [];
            foreach ($allAttachments as $att) {
                if (file_exists($att['path'])) {
                    $email->attachFromPath(
                        $att['path'],
                        $att['name'],
                        $att['mime']
                    );
                    $attachmentsList[] = [
                        'filename'     => $att['name'],
                        'size'         => $att['size'],
                        'download_url' => null, // akan diisi setelah sync IMAP
                        'mime_type'    => $att['mime'],
                    ];
                    Log::info('Attachment added to Sent Items', [
                        'name' => $att['name'],
                        'path' => $att['path'],
                        'mime' => $att['mime'],
                    ]);
                } else {
                    Log::error('Attachment file not found for Sent Items', ['path' => $att['path']]);
                }
            }

            $raw = $email->toString();
            $sentFolder->appendMessage($raw, ["\\Seen"]);

            Log::info("Email berhasil disimpan ke Sent Items.", [
                'to' => $to,
                'subject' => $subject,
                'attachments_count' => count($attachmentsList),
            ]);

            return [
                'uid'           => random_int(100, 999), // sementara, akan diupdate setelah sync IMAP
                'folder'        => 'Sent Items',
                'messageId'     => $email->getHeaders()->get('Message-ID')?->getBodyAsString() ?? uniqid(),
                'sender'        => "Me",
                'senderEmail'   => config('mail.from.address'),
                'subject'       => $subject,
                'preview'       => \Illuminate\Support\Str::limit(strip_tags($body ?? ""), 120),
                'timestamp'     => now()->format('d F Y, H:i T'),
                'seen'          => true,
                'flagged'       => false,
                'answered'      => false,
                'recipients'    => [
                    ['email' => $to]
                ],
                'body' => [
                    'text' => strip_tags($body ?? ""),
                    'html' => $body ?? "",
                ],
                'rawAttachments' => $attachmentsList,
            ];
        } catch (\Exception $e) {
            Log::error('Gagal simpan ke Sent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function updateSentCache(array $emailData)
    {
        $sentKey = $this->cachePrefix . 'folder:sent';
        $cachedSent = Redis::get($sentKey);

        if ($cachedSent) {
            $emails = json_decode($cachedSent, true);
            array_unshift($emails, $emailData);
            Redis::setex($sentKey, $this->cacheTTL, json_encode($emails));
        } else {
            Redis::setex($sentKey, $this->cacheTTL, json_encode([$emailData]));
        }

        $allKey = $this->cachePrefix . 'all_folders';
        $cachedAll = Redis::get($allKey);

        if ($cachedAll) {
            $allFolders = json_decode($cachedAll, true);
            if (!isset($allFolders['sent'])) {
                $allFolders['sent'] = [];
            }
            array_unshift($allFolders['sent'], $emailData);
            Redis::setex($allKey, $this->cacheTTL, json_encode($allFolders));
        }

        Log::info("Sent & all_folders cache updated after sending email.", [
            'attachments_count' => count($emailData['rawAttachments']),
        ]);
    }
}
