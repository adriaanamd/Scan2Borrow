<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\RequestContext;
use App\Http\Responses\RedirectResponse;

final class AuthorizationMiddleware
{
    public function __construct(
        private readonly AuthorizationPolicy $policy,
    ) {
    }

    /**
     * @param list<Role> $allowedRoles
     */
    public function guard(
        RequestContext $context,
        array $allowedRoles,
        bool $guestOnly = false,
    ): ?RedirectResponse {
        if ($this->policy->allows($context->principal(), $allowedRoles, $guestOnly)) {
            return null;
        }

        return new RedirectResponse(302, $this->homeFor($context->principal()));
    }

    public function homeFor(?Principal $principal): string
    {
        if ($principal === null) {
            return '/login';
        }

        return match ($principal->role()) {
            Role::ADMIN, Role::LIBRARIAN => '/staff/dashboard',
            Role::STUDENT => '/student/dashboard',
            Role::TEACHER => '/teacher/dashboard',
            Role::GUEST => '/guest/dashboard',
        };
    }
}
