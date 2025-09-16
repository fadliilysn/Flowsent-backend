<?php

namespace App\Jobs;

use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Handle job execution.
     */
    public function handle(): void
    {
        try {
            $service = new EmailService();
            // pakai true biar selalu refresh cache (tidak ambil dari cache lama)
            $emails = $service->fetchAllEmails(true);

            Log::info("SyncEmailsJob berhasil, folder: " . implode(", ", array_keys($emails)));
        } catch (\Exception $e) {
            Log::error("SyncEmailsJob gagal: " . $e->getMessage());
        }
    }
}
