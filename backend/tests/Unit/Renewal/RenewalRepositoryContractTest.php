<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Infrastructure\Persistence\RenewalRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RenewalRepositoryContractTest extends TestCase
{
    public function testRepositoryExposesBorrowerAndLibrarianWorkflow(): void
    {
        foreach (['find', 'listForUser', 'listPending', 'hasPendingForLoan', 'hasApprovedForLoan', 'create', 'approve', 'reject', 'cancel'] as $method) {
            self::assertTrue(method_exists(RenewalRepositoryInterface::class, $method), $method . ' is missing.');
        }

        self::assertSame(5, (new ReflectionMethod(RenewalRepositoryInterface::class, 'create'))->getNumberOfParameters());
        self::assertSame(DateTimeImmutable::class, (string) (new ReflectionMethod(RenewalRepositoryInterface::class, 'approve'))->getParameters()[3]->getType());
    }
}
