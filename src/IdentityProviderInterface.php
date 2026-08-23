<?php

declare(strict_types=1);

namespace Quillstack\Auth;

/**
 * Turns a token into whoever it belongs to.
 *
 * Where the tokens are kept, and what a user is, belong to the application — this is the one
 * thing the rest of it has to know.
 */
interface IdentityProviderInterface
{
    /**
     * The identity this token stands for, or null where it stands for nobody.
     *
     * A token which has expired, been revoked, or never existed are the same answer here:
     * saying which of the three it was tells whoever is guessing that they are close.
     */
    public function findByToken(string $token): ?Identity;
}
