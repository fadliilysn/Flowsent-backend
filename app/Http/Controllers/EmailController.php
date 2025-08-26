<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EmailService;
use App\Services\MailerService;
use Webklex\PHPIMAP\Message;

class EmailController extends Controller
{
    protected $emailService;
    protected $mailerService;

    public function __construct(EmailService $emailService, MailerService $mailerService)
    {
        $this->emailService = $emailService;
        $this->mailerService = $mailerService;
    }

    public function inbox()
    {
        return response()->json($this->emailService->getInbox());
    }

    public function sent()
    {
        return response()->json($this->emailService->getSent());
    }

    public function draft()
    {
        return response()->json($this->emailService->getDrafts());
    }

    public function delete()
    {
        return response()->json($this->emailService->getDeleteItem());
    }

    public function junk()
    {
        return response()->json($this->emailService->getJunk());
    }

    public function all()
    {
        // ✅ sekali fetch semua folder (inbox, sent, draft, deleted, junk)
        $emails = $this->emailService->fetchAllEmails();
        return response()->json($emails);
    }

    public function show($folder, $uid)
    {
        $folderName = $this->emailService->resolveFolder($folder);

        $email = $this->emailService->getEmailByUid($folderName, $uid);

        if (!$email) {
        return response()->json([
            'error' => "Email dengan UID $uid tidak ditemukan di folder $folderName"
        ], 404);
    }
        return response()->json($email);
    }


    public function send(Request $request)
    {
        $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string',
            'body'    => 'required|string',
        ]);

        $this->mailerService->sendEmail($request->to, $request->subject, $request->body);

        return response()->json(['status' => 'Email sent successfully']);
    }
}
