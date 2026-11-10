<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\SessionService;
use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Auth\Principal;
use App\Domain\Auth\StaffAccessPolicy;
use App\Domain\Auth\StaffAccessProfile;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\PageAccessAuthorizerInterface;
use App\Http\Routing\PageRoute;

final class SessionPageAccessAuthorizer implements PageAccessAuthorizerInterface
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly AuthorizationPolicy $policy,
        private readonly AuthorizationMiddleware $middleware,
        private readonly ?StaffAccessPolicy $access = null,
    ) {
    }

    public function allows(ServerRequest $request, PageRoute $route): bool
    {
        if ($route->isPublic()) {
            return !$route->redirectsAuthenticated() || $this->principal() === null;
        }

        $principal = $this->principal();
        if (!$this->policy->allows(
            $principal,
            array_map(
                static fn (string $role): \App\Domain\Auth\Role => \App\Domain\Auth\Role::from($role),
                $route->allowedRoles(),
            ),
            $route->requiresGuest(),
        )) {
            return false;
        }

        return $this->profileAllows($principal, $route->requiredProfile());
    }

    public function denialLocation(ServerRequest $request, PageRoute $route): string
    {
        if ($route->requiresGuest()) {
            return $request->applicationPath('/guest/registration');
        }

        return $request->applicationPath($this->middleware->homeFor($this->principal()));
    }

    private function principal(): ?Principal
    {
        $identity = $this->sessions->current();
        if ($identity !== null) {
            return new Principal($identity->userId(), $identity->role(), $identity->accessProfile());
        }

        $guestId = $this->sessions->currentGuestId();

        return $guestId === null ? null : new Principal($guestId, \App\Domain\Auth\Role::GUEST);
    }

    private function profileAllows(?Principal $principal, ?StaffAccessProfile $requiredProfile): bool
    {
        if ($requiredProfile === null) {
            return true;
        }

        $access = $this->access ?? new StaffAccessPolicy();

        return $requiredProfile === StaffAccessProfile::SYSTEM_ADMIN
            ? $access->isSystemAdmin($principal)
            : $access->isLibraryAide($principal);
    }
}
