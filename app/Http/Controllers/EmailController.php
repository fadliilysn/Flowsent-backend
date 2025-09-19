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

    /**
     * Ambil semua folder sekaligus (cache-first).
     * Bisa pakai ?refresh=1 untuk force refresh dari IMAP.
     */
    public function all(Request $request)
    {
        try {
            $forceRefresh = $request->boolean('refresh', false);
            $emails = $this->emailService->fetchAllEmails($forceRefresh);

            return response()->json($emails);
        } catch (\Exception $e) {
            Log::error('Error fetching all emails: ' . $e->getMessage());

            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal mengambil semua email',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ambil email dari 1 folder (cache-first).
     * Bisa pakai ?refresh=1 untuk force refresh.
     */
    public function folder(Request $request, $folderKey)
    {
        try {
            $forceRefresh = $request->boolean('refresh', false);
            $emails = $this->emailService->getFolderEmails($folderKey, $forceRefresh);

            return response()->json([
                'status' => 'success',
                'data'   => $emails,
                'folder' => $folderKey,
                'cached' => !$forceRefresh,
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching folder {$folderKey}: " . $e->getMessage());

            return response()->json([
                'status'  => 'fail',
                'message' => "Gagal mengambil email dari folder {$folderKey}",
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function deletePermanentAll()
    {
        $result = $this->emailService->deletePermanentAll();

        return response()->json($result, $result['success'] ? 200 : 500);
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
                $request->folder,
                $request->email_ids,
                $request->target_folder
            );

            return response()->json([
                'status'  => 'success',
                'message' => count($moved) . ' email berhasil dipindahkan',
                'moved'   => $moved,
            ]);
        } catch (\Exception $e) {
            Log::error("Error moving email: " . $e->getMessage());

            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal memindahkan email',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'folder'   => 'required|string',
            'email_id' => 'required'
        ]);

        try {
            $this->emailService->markAsRead($request->folder, $request->email_id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Email berhasil ditandai sebagai sudah dibaca',
            ]);
        } catch (\Exception $e) {
            Log::error("Error markAsRead: " . $e->getMessage());

            return response()->json([
                'status'  => 'fail',
                'message' => 'Gagal menandai email sebagai sudah dibaca',
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
            $this->emailService->markAsFlagged($request->folder, $request->email_id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Email berhasil ditandai sebagai flagged',
            ]);
        } catch (\Exception $e) {
            Log::error("Error markAsFlagged: " . $e->getMessage());

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
            $this->emailService->markAsUnflagged($request->folder, $request->email_id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Flag berhasil dihapus dari email',
            ]);
        } catch (\Exception $e) {
            Log::error("Error markAsUnflagged: " . $e->getMessage());

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
            'to'           => 'nullable|email',
            'subject'      => 'nullable|string|max:255',
            'body'         => 'nullable|string',
            'attachments'  => 'nullable|array',
            'attachments.*' => 'file|max:10240'
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
            ]);
        } catch (\Exception $e) {
            Log::error("Error saveDraft: " . $e->getMessage());

            return response()->json([
                'status'  => 'fail',
                'message' => 'Failed to save draft',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function downloadAttachment($uid, $filename)
    {
        try {
            $filename = urldecode($filename);
            $attachmentData = $this->emailService->downloadAttachment($uid, $filename);

            return response()->streamDownload(
                fn() => print($attachmentData['content']),
                $attachmentData['filename'],
                ['Content-Type' => $attachmentData['mime_type']]
            );
        } catch (\Exception $e) {
            Log::error("Error downloadAttachment: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Preview attachment inline if possible (images, PDFs, text)
    public function previewAttachment(Request $request, $uid, $filename)
    {
        try {
            $attachment = $this->emailService->downloadAttachment($uid, $filename);

            $fileName = $attachment['filename'] ?? 'attachment';
            $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';

            // override mime kalau IMAP balikin octet-stream
            if ($mimeType === 'application/octet-stream') {
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileName)) {
                    $mimeType = 'image/jpeg';
                } elseif (preg_match('/\.pdf$/i', $fileName)) {
                    $mimeType = 'application/pdf';
                } elseif (preg_match('/\.(txt|log)$/i', $fileName)) {
                    $mimeType = 'text/plain';
                }
            }

            // Preview inline hanya untuk file yang didukung
            if (preg_match('/(image|pdf|text)/i', $mimeType)) {
                return response($attachment['content'])
                    ->header('Content-Type', $mimeType)
                    ->header('Content-Disposition', 'inline; filename="'.$fileName.'"');
            }

            // fallback: force download dengan nama asli
            return response($attachment['content'])
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to preview attachment',
                'message' => $e->getMessage()
            ], 500);
        }
    }




    // Tambahkan untuk simpan attachment sementara
    public function uploadAttachment(Request $request)
    {
        $file = $request->file('attachment');
        $path = $file->store('tmp/attachments');

        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType()
        ]);
    }


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
