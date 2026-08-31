<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Infrastructure\Persistence\ProfileChangeNotificationInterface;
use App\Infrastructure\Persistence\ProfileChangeRequestRepositoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ProfilePersistenceContractTest extends TestCase
{
    public function testRepositoryContractExposesProfileApprovalOperations(): void
    {
        foreach (['profile', 'pendingForUser', 'create', 'pendingRequests', 'decide'] as $method) {
            self::assertTrue(method_exists(ProfileChangeRequestRepositoryInterface::class, $method), $method);
            self::assertSame(ReflectionMethod::IS_PUBLIC, (new ReflectionMethod(ProfileChangeRequestRepositoryInterface::class, $method))->getModifiers() & ReflectionMethod::IS_PUBLIC);
        }
    }

    public function testNotificationContractExposesTargetedRecipients(): void
    {
        self::assertTrue(method_exists(ProfileChangeNotificationInterface::class, 'notifyAdministrators'));
        self::assertTrue(method_exists(ProfileChangeNotificationInterface::class, 'notifyBorrower'));
    }
}
