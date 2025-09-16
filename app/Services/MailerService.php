<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;

class MailerService
{
    public function sendEmail($to, $subject, $body, $attachments = [])
    {
        try {
            Mail::send([], [], function ($message) use ($to, $subject, $body, $attachments) {
                $message->to($to)
                    ->subject($subject)
                    ->html($body);

                // Handle attachments dengan pengecekan yang lebih detail
                if (!empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        // Pastikan file attachment valid
                        if ($attachment && $attachment->isValid()) {
                            $message->attach(
                                $attachment->getRealPath(),
                                [
                                    'as' => $attachment->getClientOriginalName(),
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

            // 2. Simpan email ke folder "Sent"
            $this->saveToSent($to, $subject, $body, $attachments);

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

            $sentFolder = $client->getFolder('Sent Items'); // sesuai server kamu

            if (!$sentFolder) {
                Log::warning("Tidak ditemukan folder Sent di akun IMAP.");
                return;
            }

            // Build email dengan Symfony\Mime
            $email = new SymfonyEmail();
            $email->from(new Address(config('mail.from.address'), "Me"))
                ->to(new Address($to))
                ->subject($subject ?? "(no subject)")
                ->text(strip_tags($body ?? ""))
                ->html($body ?? "");

            foreach ($attachments as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $email->attachFromPath(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                        $file->getMimeType()
                    );
                }
            }

            // SymfonyEmail akan generate Message-ID & Date otomatis
            $raw = $email->toString();
            $sentFolder->appendMessage($raw, ["\\Seen"]);

            Log::info("Email berhasil disimpan ke Sent Items.");
        } catch (\Exception $e) {
            Log::error('Gagal simpan ke Sent: ' . $e->getMessage());
        }
    }
}
