<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\EmailService;
use App\Services\MailerService;

class EmailController extends Controller
{
    protected $emailService;
    protected $mailerService;

    public function __construct(EmailService $emailService, MailerService $mailerService)
    {
        $this->emailService = $emailService;
        $this->mailerService = $mailerService;
    }

    public function all()
    {
        try {
            $emails = $this->emailService->fetchAllEmails();
            return response()->json($emails);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal mengambil semua email',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function deletePermanentAll()
    {
        $result = $this->emailService->deletePermanentAll();

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 500);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'folder'   => 'required|string',
            'email_id' => 'required'
        ]);

        try {
            $this->emailService->markAsRead(
                $request->input('folder'),
                $request->input('email_id')
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Email berhasil ditandai sebagai sudah dibaca',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal menandai email sebagai sudah dibaca',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function move(Request $request)
    {
        $request->validate([
            'folder'        => 'required|string',
            'email_ids'     => 'required|array',
            'target_folder' => 'required|string'
        ]);

        try {
            $moved = $this->emailService->moveEmail(
                $request->input('folder'),
                $request->input('email_ids'),
                $request->input('target_folder')
            );

            if (empty($moved)) {
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'Tidak ada email ditemukan untuk dipindahkan',
                ], 404);
            }

            return response()->json([
                'status'  => 'success',
                'message' => count($moved) . ' email berhasil dipindahkan',
                'moved'   => $moved,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal memindahkan email',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function markAsFlagged(Request $request)
    {
        $request->validate([
            'folder'   => 'required|string',
            'email_id' => 'required'
        ]);

        try {
            $this->emailService->markAsFlagged(
                $request->input('folder'),
                $request->input('email_id')
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Email berhasil ditandai sebagai flagged',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal menandai email sebagai flagged',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function markAsUnflagged(Request $request)
    {
        $request->validate([
            'folder'   => 'required|string',
            'email_id' => 'required'
        ]);

        try {
            $this->emailService->markAsUnflagged(
                $request->input('folder'),
                $request->input('email_id')
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Flag berhasil dihapus dari email',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal menghapus flag dari email',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function saveDraft(Request $request)
    {
        $request->validate([
            'to'           => 'nullable|email', // draft bisa tanpa penerima
            'subject'      => 'nullable|string|max:255',
            'body'         => 'nullable|string',
            'attachments'  => 'nullable|array',
            'attachments.*' => 'file|max:10240' // max 10MB
        ]);

        try {
            $attachments = $request->hasFile('attachments')
                ? $request->file('attachments')
                : [];

            $draft = $this->emailService->saveDraft(
                $request->to,
                $request->subject,
                $request->body,
                $attachments
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Draft saved successfully',
                'draft'   => $draft
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => 'Failed to save draft',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Download attachment langsung (stream ke browser)
     */
    public function downloadAttachment($uid, $filename)
    {
        try {
            $filename = urldecode($filename); // decode filename dari URL

            $attachmentData = $this->emailService->downloadAttachment($uid, $filename);

            if (!$attachmentData || empty($attachmentData['content'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Attachment not found or empty'
                ], 404);
            }

            // Gunakan streamDownload agar tidak mismatch Content-Length
            return response()->streamDownload(
                function () use ($attachmentData) {
                    echo $attachmentData['content'];
                },
                $attachmentData['filename'],
                [
                    'Content-Type' => $attachmentData['mime_type'],
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk mengirim email
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(Request $request)
    {
        try {
            // Validasi request
            $request->validate([
                'to'           => 'required|email',
                'subject'      => 'required|string|max:64',
                'body'         => 'required|string',
                'attachments'  => 'nullable|array',
                'attachments.*' => 'file|max:10240'  // max 10MB per file
            ]);

            $attachments = [];
            if ($request->hasFile('attachments')) {
                $attachments = $request->file('attachments');

                Log::info('Attachments received: ' . count($attachments));
                foreach ($attachments as $index => $file) {
                    Log::info("Attachment {$index}: " . $file->getClientOriginalName() .
                        ' - Size: ' . $file->getSize() .
                        ' - MIME: ' . $file->getMimeType());
                }
            } else {
                Log::info('No attachments received');
            }

            // Kirim email via service
            $this->mailerService->sendEmail(
                $request->to,
                $request->subject,
                $request->body,
                $attachments
            );

            return response()->json([
                'status'           => 'success',
                'message'            => 'Email sent successfully',
                'to'                => $request->to,
                'subject'           => $request->subject,
                'attachments_count' => count($attachments)
            ]);
        } catch (\Exception $e) {
            Log::error('Email sending error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message'  => 'Error sending email',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
