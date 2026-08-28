<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\SessionService;
use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Auth\Principal;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\PageAccessAuthorizerInterface;
use App\Http\Routing\PageRoute;

final class SessionPageAccessAuthorizer implements PageAccessAuthorizerInterface
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly AuthorizationPolicy $policy,
        private readonly AuthorizationMiddleware $middleware,
    ) {
    }

    public function allows(ServerRequest $request, PageRoute $route): bool
    {
        if ($route->isPublic()) {
            return true;
        }

        return $this->policy->allows(
            $this->principal(),
            array_map(
                static fn (string $role): \App\Domain\Auth\Role => \App\Domain\Auth\Role::from($role),
                $route->allowedRoles(),
            ),
            $route->requiresGuest(),
        );
    }

    public function denialLocation(ServerRequest $request, PageRoute $route): string
    {
        return $this->middleware->homeFor($this->principal());
    }

    private function principal(): ?Principal
    {
        $identity = $this->sessions->current();
        if ($identity === null) {
            return null;
        }

        return new Principal($identity->userId(), $identity->role());
    }
}
