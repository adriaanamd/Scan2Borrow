<?php

declare(strict_types=1);

namespace App\Application\Services;

require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php';
require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PHPMailer.php';
require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

final class SmtpEmailSender implements EmailSenderInterface
{
    public function isConfigured(): bool
    {
        return $this->environment('MAIL_USERNAME') !== ''
            && $this->environment('MAIL_PASSWORD') !== '';
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->environment('MAIL_HOST', 'smtp.gmail.com');
            $mail->Port = (int) $this->environment('MAIL_PORT', '587');
            $mail->SMTPAuth = true;
            $mail->Username = $this->environment('MAIL_USERNAME');
            $mail->Password = $this->environment('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom(
                $this->environment('MAIL_FROM', $mail->Username),
                $this->environment('MAIL_FROM_NAME', 'Scan2Borrow Library Management'),
            );

            $logo = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'logo.png';
            if (is_file($logo)) {
                $mail->addEmbeddedImage(
                    $logo,
                    'scan2borrow-school-seal',
                    'scan2borrow-school-seal.png',
                    PHPMailer::ENCODING_BASE64,
                    'image/png',
                );
            }

            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $mail->send();
        } catch (Throwable) {
            return false;
        }
    }

    private function environment(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
