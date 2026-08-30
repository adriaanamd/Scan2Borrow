<?php

declare(strict_types=1);

namespace App\Application\Services;

final class SchoolEmailTemplate
{
    public static function render(string $eyebrow, string $title, string $body): string
    {
        return '<!doctype html><html lang="en"><body style="margin:0;background:#f3f6fa;color:#23384a;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;background:#f3f6fa;width:100%;">'
            . '<tr><td align="center" style="padding:32px 16px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;background:#ffffff;border:1px solid #d4e0e8;max-width:600px;width:100%;">'
            . '<tr><td align="center" style="background:#102f52;border-bottom:5px solid #d4a72c;padding:28px 24px;">'
            . '<img src="cid:scan2borrow-school-seal" width="88" height="88" alt="Binalbagan Catholic College seal" style="display:block;border:0;border-radius:50%;height:88px;margin:0 auto 16px;width:88px;">'
            . '<div style="color:#ffffff;font-size:16px;font-weight:bold;letter-spacing:.04em;">BINALBAGAN CATHOLIC COLLEGE</div>'
            . '<div style="color:#dce8f1;font-size:12px;letter-spacing:.08em;margin-top:7px;text-transform:uppercase;">Scan2Borrow Library Services</div>'
            . '</td></tr>'
            . '<tr><td style="padding:34px 32px 30px;">'
            . '<div style="color:#075985;font-size:11px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;">'
            . self::escape($eyebrow)
            . '</div><h1 style="color:#102f52;font-size:25px;line-height:1.25;margin:9px 0 16px;">'
            . self::escape($title)
            . '</h1>' . $body
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;border-top:1px solid #d4e0e8;padding:20px 32px;">'
            . '<div style="color:#102f52;font-size:13px;font-weight:bold;">Library Management Office</div>'
            . '<div style="color:#63798b;font-size:12px;line-height:1.5;margin-top:5px;">Binalbagan Catholic College<br>Scan2Borrow Library Services</div>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
