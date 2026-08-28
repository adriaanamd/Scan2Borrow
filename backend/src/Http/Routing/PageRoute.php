<?php

declare(strict_types=1);

namespace App\Http\Routing;

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
}
