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

        $body = '<p style="font-size:15px;line-height:1.65;margin:0 0 14px;">Dear <strong>' . $this->escape($name) . '</strong>,</p>'
            . '<p style="font-size:15px;line-height:1.65;margin:0 0 22px;">This is an official notification from the Scan2Borrow Library Services regarding your borrowed books.</p>'
            . '<div style="overflow-x:auto;"><table style="border-collapse:collapse;width:100%;font-size:13px;">'
            . '<tr style="background:#e8f4fa;color:#102f52;"><th align="left" style="border-bottom:2px solid #075985;padding:10px 8px;">Book Title</th><th align="left" style="border-bottom:2px solid #075985;padding:10px 8px;">Borrow Date</th><th align="left" style="border-bottom:2px solid #075985;padding:10px 8px;">Due Date</th><th align="left" style="border-bottom:2px solid #075985;padding:10px 8px;">Status</th></tr>'
            . $rows . '</table></div>'
            . '<p style="font-size:14px;line-height:1.6;margin:22px 0 0;">Kindly return all borrowed books on or before their due dates to avoid additional fines.</p>';

        return SchoolEmailTemplate::render('Library account notice', 'Your borrowed book record', $body);
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
