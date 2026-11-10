<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\BorrowerContactNotification;
use App\Infrastructure\Persistence\ContactNotificationDeliveryRepositoryInterface;
use DateTimeImmutable;

final class BorrowerContactNotificationService implements BorrowerContactNotificationInterface
{
    public function __construct(
        private readonly ContactNotificationDeliveryRepositoryInterface $deliveries,
        private readonly EmailSenderInterface $email,
        private readonly ?SmsSenderInterface $sms = null,
    ) {
    }

    public function dispatch(BorrowerContactNotification $notification): void
    {
        if ($notification->recipientId < 1 || trim($notification->borrowingReference) === '' || $notification->titles === []) {
            return;
        }

        $recipient = $this->deliveries->findRecipient($notification->recipientType, $notification->recipientId);
        if ($recipient === null) {
            return;
        }

        foreach (['email', 'sms'] as $channel) {
            $destination = $channel === 'email' ? $recipient['email'] : $recipient['phone'];
            $subject = $channel === 'email' ? $this->subject($notification->eventType) : null;
            $message = $channel === 'email'
                ? $this->htmlMessage($recipient['name'], $notification)
                : $this->smsMessage($recipient['name'], $notification);
            $destinationValue = is_string($destination) ? trim($destination) : '';
            $hasDestination = $destinationValue !== '';
            $status = $hasDestination ? 'pending' : 'skipped';
            $error = $hasDestination ? null : 'No contact value is on file.';

            $deliveryId = $this->deliveries->createDelivery(
                $notification->recipientType,
                $notification->recipientId,
                $notification->eventType,
                $notification->borrowingReference,
                $channel,
                $hasDestination ? $destinationValue : null,
                $subject,
                $message,
                $status,
                $error,
            );
            if ($deliveryId === null || !$hasDestination) {
                continue;
            }

            $this->send($deliveryId, $channel, $destinationValue, $recipient['name'], $subject, $message);
        }
    }

    private function send(int $deliveryId, string $channel, string $destination, string $name, ?string $subject, string $message): void
    {
        if ($channel === 'email') {
            if (!$this->email->isConfigured()) {
                $this->deliveries->markFailed($deliveryId, 'Email is not configured.');
                return;
            }

            try {
                $sent = $this->email->send($destination, $name, $subject ?? 'Scan2Borrow Notification', $message);
            } catch (\Throwable) {
                $this->deliveries->markFailed($deliveryId, 'Could not send email.');

                return;
            }
            if (!$sent) {
                $this->deliveries->markFailed($deliveryId, 'Could not send email.');

                return;
            }

            $this->deliveries->markSent($deliveryId, new DateTimeImmutable());

            return;
        }

        if ($this->sms === null) {
            $this->deliveries->markFailed($deliveryId, 'SMS is not configured.');

            return;
        }

        try {
            $this->sms->send($destination, $message);
        } catch (\Throwable $exception) {
            $error = trim($exception->getMessage());
            $this->deliveries->markFailed($deliveryId, $error !== '' ? $error : 'Could not send SMS.');

            return;
        }

        $this->deliveries->markSent($deliveryId, new DateTimeImmutable());
    }

    private function subject(string $eventType): string
    {
        return $eventType === 'borrow_approved'
            ? 'Scan2Borrow - Borrowing Approved'
            : 'Scan2Borrow - Borrow Request Received';
    }

    private function htmlMessage(string $name, BorrowerContactNotification $notification): string
    {
        $titleSummary = $this->titleSummary($notification, true);
        $status = $notification->eventType === 'borrow_approved'
            ? 'Your borrowing request was approved.'
            : 'Your borrowing request was received and is pending staff approval.';
        $due = $this->dueText($notification->dueDate);
        $dueLine = $due === '' ? '' : '<p>Due date: <strong>' . $this->escape($due) . '</strong></p>';

        return '<!doctype html><html><body>'
            . '<p>Dear <strong>' . $this->escape($name !== '' ? $name : 'Borrower') . '</strong>,</p>'
            . '<p>' . $this->escape($status) . '</p>'
            . '<p>Book(s): <strong>' . $titleSummary . '</strong></p>'
            . '<p>Reference: <strong>' . $this->escape($notification->borrowingReference) . '</strong></p>'
            . $dueLine
            . '<p>Scan2Borrow Library Services</p>'
            . '</body></html>';
    }

    private function smsMessage(string $name, BorrowerContactNotification $notification): string
    {
        $status = $notification->eventType === 'borrow_approved'
            ? 'approved'
            : 'received and pending staff approval';
        $due = $this->dueText($notification->dueDate);
        $dueLine = $due === '' ? '' : ' Due ' . $due . '.';

        return 'Scan2Borrow: Dear ' . ($name !== '' ? $name : 'Borrower') . ', your borrow request was ' . $status
            . '. Book(s): ' . $this->titleSummary($notification, false)
            . '. Ref: ' . $notification->borrowingReference . '.' . $dueLine;
    }

    private function titleSummary(BorrowerContactNotification $notification, bool $html): string
    {
        $titles = [];
        foreach ($notification->titles as $item) {
            $title = trim($item['title']);
            if ($title === '') {
                continue;
            }
            $quantity = max(1, $item['quantity']);
            $label = $quantity > 1 ? $title . ' (' . $quantity . ' copies)' : $title;
            $titles[] = $html ? $this->escape($label) : $label;
        }

        return implode(', ', $titles);
    }

    private function dueText(?string $dueDate): string
    {
        if ($dueDate === null || trim($dueDate) === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($dueDate));

        return $parsed === false ? trim($dueDate) : $parsed->format('M d, Y');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
