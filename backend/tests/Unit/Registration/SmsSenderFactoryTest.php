<?php

declare(strict_types=1);

namespace Tests\Unit\Registration;

use App\Application\Services\NullSmsSender;
use App\Application\Services\SmsSenderFactory;
use App\Application\Services\TextBeeSmsSender;
use PHPUnit\Framework\TestCase;

final class SmsSenderFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SMS_ENABLED');
        putenv('SMS_PROVIDER');
        parent::tearDown();
    }

    public function testTextBeeIsSelectedWhenSmsIsEnabled(): void
    {
        putenv('SMS_ENABLED=true');
        putenv('SMS_PROVIDER=textbee');

        self::assertInstanceOf(TextBeeSmsSender::class, SmsSenderFactory::create());
    }

    public function testUnavailableProviderUsesTheFailingFallbackSender(): void
    {
        putenv('SMS_ENABLED=false');
        putenv('SMS_PROVIDER=textbee');

        self::assertInstanceOf(NullSmsSender::class, SmsSenderFactory::create());
    }
}
