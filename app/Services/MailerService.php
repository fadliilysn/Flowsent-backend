<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Webklex\IMAP\Facades\Client;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;

class MailerService
{
    protected $cachePrefix = 'emails:';
    protected $cacheTTL = 3600; // 1 jam

    public function sendEmail($to, $subject, $body, $attachments = [])
    {
        try {
            Mail::send([], [], function ($message) use ($to, $subject, $body, $attachments) {
                $message->to($to)
                    ->subject($subject)
                    ->html($body);

                // Handle attachments
                if (!empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        if ($attachment && $attachment->isValid()) {
                            $message->attach(
                                $attachment->getRealPath(),
                                [
                                    'as'   => $attachment->getClientOriginalName(),
                                    'mime' => $attachment->getMimeType()
                                ]
                            );

                            Log::info('Attachment added: ' . $attachment->getClientOriginalName());
                        } else {
                            Log::warning('Invalid attachment found');
                        }
                    }
                }
            });

            // Simpan email ke folder Sent + update cache
            $emailData = $this->saveToSent($to, $subject, $body, $attachments);

            // Update cache folder "sent"
            if ($emailData) {
                $this->updateSentCache($emailData);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Email sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Simpan salinan email ke folder Sent via IMAP
     */
    private function saveToSent($to, $subject, $body, $attachments = [])
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
            foreach ($attachments as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $email->attachFromPath(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                        $file->getMimeType()
                    );

                    $attachmentsList[] = [
                        'filename'     => $file->getClientOriginalName(),
                        'size'         => $file->getSize(),
                        'download_url' => null // belum ada URL download (butuh fetch ulang dari IMAP)
                    ];
                }
            }

            $raw = $email->toString();
            $sentFolder->appendMessage($raw, ["\\Seen"]);

            Log::info("Email berhasil disimpan ke Sent Items.");

            // 🔑 Bentuk data mirip parseEmail() di EmailService
            return [
                'uid'           => random_int(100, 120), // belum tahu sebelum sync IMAP
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
            Log::error('Gagal simpan ke Sent: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update cache folder Sent agar langsung ter-refresh
     */
    private function updateSentCache(array $emailData)
    {
        // 🔹 Update cache folder:sent
        $sentKey = $this->cachePrefix . 'folder:sent';
        $cachedSent = Redis::get($sentKey);

        if ($cachedSent) {
            $emails = json_decode($cachedSent, true);
            array_unshift($emails, $emailData);
            Redis::setex($sentKey, $this->cacheTTL, json_encode($emails));
        } else {
            Redis::setex($sentKey, $this->cacheTTL, json_encode([$emailData]));
        }

        // 🔹 Update cache all_folders (pakai struktur array asosiatif)
        $allKey = $this->cachePrefix . 'all_folders';
        $cachedAll = Redis::get($allKey);

        if ($cachedAll) {
            $allFolders = json_decode($cachedAll, true);

            if (!isset($allFolders['sent'])) {
                $allFolders['sent'] = [];
            }

            // prepend ke folder sent di dalam all_folders
            array_unshift($allFolders['sent'], $emailData);

            Redis::setex($allKey, $this->cacheTTL, json_encode($allFolders));
        }

        Log::info("Sent & all_folders cache updated after sending email.");
    }
}
