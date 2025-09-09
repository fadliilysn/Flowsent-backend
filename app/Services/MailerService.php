<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

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

            // Cari folder Sent yang tersedia
            $folders = $client->getFolders();
            $sentFolder = collect($folders)->first(function ($f) {
                return in_array(strtolower($f->name), ['sent', 'sent items', 'inbox.sent']);
            });

            if ($sentFolder) {
                $fromEmail = config('mail.from.address');
                $fromName  = 'Me';
                $date      = date('r'); // RFC2822 format

                if (empty($attachments)) {
                    // --- Hanya HTML body ---
                    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
                    $headers .= "To: {$to}\r\n";
                    $headers .= "Subject: {$subject}\r\n";
                    $headers .= "Date: {$date}\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

                    $rawMessage = $headers . $body;
                } else {
                    // --- HTML + Attachment ---
                    $boundary = md5(time());

                    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
                    $headers .= "To: {$to}\r\n";
                    $headers .= "Subject: {$subject}\r\n";
                    $headers .= "Date: {$date}\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

                    $rawMessage = $headers;

                    // Bagian HTML
                    $rawMessage .= "--{$boundary}\r\n";
                    $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $rawMessage .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                    $rawMessage .= $body . "\r\n\r\n";

                    // Bagian attachment
                    foreach ($attachments as $file) {
                        // Handle UploadedFile dari request
                        if ($file instanceof \Illuminate\Http\UploadedFile) {
                            $filename = $file->getClientOriginalName();
                            $mimeType = $file->getMimeType();
                            $fileData = chunk_split(base64_encode(file_get_contents($file->getRealPath())));
                        } else {
                            // fallback kalau dikirim path string
                            if (!file_exists($file)) {
                                continue;
                            }
                            $filename = basename($file);
                            $mimeType = mime_content_type($file);
                            $fileData = chunk_split(base64_encode(file_get_contents($file)));
                        }

                        $rawMessage .= "--{$boundary}\r\n";
                        $rawMessage .= "Content-Type: {$mimeType}; name=\"{$filename}\"\r\n";
                        $rawMessage .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
                        $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
                        $rawMessage .= $fileData . "\r\n\r\n";
                    }

                    // Tutup boundary
                    $rawMessage .= "--{$boundary}--\r\n";
                }

                $sentFolder->appendMessage($rawMessage);

                Log::info("Email berhasil disimpan ke Sent folder: " . $sentFolder->name);
            } else {
                Log::warning("Tidak ditemukan folder Sent di akun IMAP.");
            }
        } catch (\Exception $e) {
            Log::error('Gagal simpan ke Sent: ' . $e->getMessage());
        }
    }
}
