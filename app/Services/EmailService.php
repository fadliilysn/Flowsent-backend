<?php

namespace App\Services;

use Webklex\IMAP\Facades\Client;

class EmailService
{
    protected $client;

    public function __construct()
    {
        $this->client = Client::account('default');
        $this->client->connect();
    }

    public function getInbox()
    {
        $folder = $this->client->getFolder('INBOX');
        $messages = $folder->messages()->all()->limit(20)->get();
        return $messages->map(fn($msg) => $this->parseEmail($msg));
    }

    public function getSent()
    {
        $folder = $this->client->getFolder('Sent Items');
        $messages = $folder->messages()->all()->limit(20)->get();
        return $messages->map(fn($msg) => $this->parseEmail($msg));
    }

    public function getDrafts()
    {
        $folder = $this->client->getFolder('Drafts');
        $messages = $folder->messages()->all()->limit(20)->get();
        return $messages->map(fn($msg) => $this->parseEmail($msg));
    }

    public function getDeleteItem()
    {
        $folder = $this->client->getFolder('Deleted Items');
        $messages = $folder->messages()->all()->limit(20)->get();
        return $messages->map(fn($msg) => $this->parseEmail($msg));
    }

    public function getJunk()
    {
        $folder = $this->client->getFolder('Junk Mail');
        $messages = $folder->messages()->all()->limit(20)->get();
        return $messages->map(fn($msg) => $this->parseEmail($msg));
    }

    public function getEmailByUid($folderName, $uid)
    {
        try {
            $folder = $this->client->getFolder($folderName);

            $uid = (int) $uid;

            $message = $folder->messages()->getMessage($uid);

            if (!$message) {
                return null;
            }

            return $this->parseEmail($message);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 🔑 Parsing email
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
                'content_type'  => $attachment->mime,
                'size'          => $attachment->size,
                'download_url'  => url("/download/uid/{$message->getUid()}/" . urlencode($attachment->name)),
            ];
        }

        // === Bentuk JSON sesuai kebutuhan frontend ===
        return [
            'uid'           => $message->getUid(),
            'messageId'     => (string) $message->getMessageId(),
            'folder'        => $message->getFolderPath(),
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
    ];

    public function resolveFolder($key)
    {
        return $this->folderMap[strtolower($key)] ?? 'INBOX';
    }
    
    /**
     * ✅ Sekali fetch semua folder
     */
    public function fetchAllEmails($limit = 20): array
    {
        $result = [];

        foreach ($this->folderMap as $key => $imapFolderName) {
            try {
                $folder = $this->client->getFolder($imapFolderName);
                $messages = $folder->messages()->all()->limit($limit)->get();

                $result[$key] = $messages->map(fn($msg) => $this->parseEmail($msg))->toArray();
            } catch (\Exception $e) {
                $result[$key] = [];
            }
        }

        return $result;
    }
}
