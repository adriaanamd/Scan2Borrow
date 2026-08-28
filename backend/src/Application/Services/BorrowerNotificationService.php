<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\StaffRepositoryInterface;

final readonly class BorrowerNotificationService
{
    public function __construct(
        private StaffRepositoryInterface $staff,
        private EmailSenderInterface $email,
    ) {
    }

    /** @return array{ok: bool, message: string} */
    public function send(int $userId, string $channel): array
    {
        $details = $this->staff->borrowerDetails($userId);
        if ($details === null) {
            return ['ok' => false, 'message' => 'Borrower not found.'];
        }
        $borrower = $details['borrower'];
        $name = $this->string($borrower['name'] ?? null);

        if ($channel === 'sms') {
            return ['ok' => false, 'message' => 'SMS is not enabled. Please configure an SMS adapter.'];
        }
        if ($channel !== 'email') {
            return ['ok' => false, 'message' => 'Unknown notification channel.'];
        }
        $address = $this->string($borrower['email'] ?? null);
        if ($address === '') {
            return ['ok' => false, 'message' => 'This borrower has no email address on file.'];
        }
        if (!$this->email->isConfigured()) {
            return ['ok' => false, 'message' => 'SMTP is not configured yet. Set MAIL_USERNAME and MAIL_PASSWORD to enable email sending.'];
        }

        $html = $this->messageHtml($name, $details['history']);
        $sent = $this->email->send($address, $name, 'Scan2Borrow - Your Borrowed Book Record', $html);

        return $sent
            ? ['ok' => true, 'message' => 'Notification sent to ' . $address . '.']
            : ['ok' => false, 'message' => 'Could not send email. Please try again.'];
    }

    /** @param list<array<string, mixed>> $history */
    private function messageHtml(string $name, array $history): string
    {
        $rows = '';
        foreach ($history as $loan) {
            if ($loan['return_date'] !== null && $loan['return_date'] !== '') {
                continue;
            }
            $rows .= '<tr><td>' . $this->escape($loan['title'] ?? '') . '</td><td>' . $this->escape($loan['borrow_date'] ?? '') . '</td><td>' . $this->escape($loan['due_date'] ?? '') . '</td><td>' . $this->escape($loan['status'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">No active borrowed books.</td></tr>';
        }

        return '<div style="font-family:Arial,sans-serif;color:#1f2937;max-width:600px;margin:auto">'
            . '<h2 style="background:#1E3A5F;color:#fff;padding:20px">&#128218; Scan2Borrow Library</h2>'
            . '<div style="padding:20px"><h3>Your Borrowed Book Record</h3>'
            . '<p>Dear <b>' . $this->escape($name) . '</b>,</p>'
            . '<p>This is an official notification from the Scan2Borrow Library regarding your borrowed books.</p>'
            . '<table style="border-collapse:collapse;width:100%"><tr><th>Book Title</th><th>Borrow Date</th><th>Due Date</th><th>Status</th></tr>' . $rows . '</table>'
            . '<p>Kindly return all borrowed books on or before their due dates to avoid additional fines.</p>'
            . '<p>Regards,<br><b>Library Management Office</b></p></div></div>';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
