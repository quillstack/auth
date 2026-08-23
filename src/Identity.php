<?php

declare(strict_types=1);

namespace Quillstack\Auth;

/**
 * Who a request is from.
 *
 * Whatever the application calls them — a user, an account, a service — this is the part
 * everything else needs: something to tell them apart, and what they are allowed to be.
 */
final class Identity
{
    /**
     * @param string[] $roles
     * @param array<string, mixed> $attributes anything else the application wants to carry
     */
    public function __construct(
        public readonly int|string $id,
        public readonly array $roles = [],
        public readonly array $attributes = []
    ) {
        //
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Whether any one of these will do — which is what a route asking for several means.
     *
     * @param string[] $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }
}
