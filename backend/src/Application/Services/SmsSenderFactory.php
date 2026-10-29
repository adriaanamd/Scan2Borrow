<?php

declare(strict_types=1);

namespace App\Application\Services;

final class SmsSenderFactory
{
    public static function create(): SmsSenderInterface
    {
        $enabled = in_array(
            strtolower(trim((string) getenv('SMS_ENABLED'))),
            ['1', 'true', 'yes', 'on'],
            true,
        );
        $provider = strtolower(trim((string) getenv('SMS_PROVIDER')));

        if ($enabled && $provider === 'textbee') {
            return new TextBeeSmsSender();
        }

        return new NullSmsSender();
    }
}
