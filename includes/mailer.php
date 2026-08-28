<?php
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
 
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    $phpmailerPath = __DIR__ . '/../PHPMailer/src/PHPMailer.php';

    if (is_file($phpmailerPath)) {
        require_once __DIR__ . '/../PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jenmargvargas@gmail.com';
            $mail->Password   = 'uhpp gmlo godl ekox';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return ['ok' => true, 'error' => ''];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $mail->ErrorInfo];
        }
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
    $ok = @mail($toEmail, $subject, $htmlBody, $headers);
    return ['ok' => $ok, 'error' => $ok ? '' : 'PHP mail() failed or PHPMailer not installed.'];
}

function mail_template(string $title, string $innerHtml): string
{
    return "
    <div style='font-family:Arial,sans-serif;color:#1f2937;max-width:600px;margin:auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden'>
        <div style='background:#1E3A5F;color:#fff;padding:20px 24px'>
            <h2 style='margin:0;font-size:20px'>&#128218; Scan2Borrow Library</h2>
        </div>
        <div style='padding:24px'>
            <h3 style='margin-top:0;color:#1E3A5F'>" . $title . "</h3>
            " . $innerHtml . "
        </div>
        <div style='background:#f8fafc;color:#6b7280;font-size:12px;padding:14px 24px'>
            This is an automated message from the Scan2Borrow Library Management System. Please do not reply.
        </div>
    </div>";
}
