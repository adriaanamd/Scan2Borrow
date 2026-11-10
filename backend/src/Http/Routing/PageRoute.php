<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Domain\Auth\StaffAccessProfile;

final readonly class PageRoute
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $path,
        private string $template,
        private array $roles = [],
        private bool $guestOnly = false,
        private bool $redirectAuthenticated = false,
        private ?StaffAccessProfile $requiredProfile = null,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function templatePath(): string
    {
        return $this->template;
    }

    /**
     * @return list<string>
     */
    public function allowedRoles(): array
    {
        return $this->roles;
    }

    public function requiresGuest(): bool
    {
        return $this->guestOnly;
    }

    public function isPublic(): bool
    {
        return $this->roles === [] && !$this->guestOnly;
    }

    public function redirectsAuthenticated(): bool
    {
        return $this->redirectAuthenticated;
    }

    public function requiredProfile(): ?StaffAccessProfile
    {
        return $this->requiredProfile;
    }
}
