<?php

declare(strict_types=1);

namespace Quillstack\Auth;

use Quillstack\Auth\Exceptions\NotAuthenticatedException;

/**
 * Tokens, made and checked the way a secret has to be.
 *
 * Two things go wrong with tokens written by hand: they are made from something guessable,
 * and they are compared with `===`, which stops at the first byte that differs and so says
 * how much of a guess was right.
 */
final class Token
{
    /**
     * How many bytes of randomness a token carries. Thirty-two is more than anything will
     * guess, and the string it prints as is 64 characters.
     */
    public const BYTES = 32;

    /**
     * A new token, from the operating system's randomness rather than from the clock, the
     * process id, or anything else somebody could have been watching.
     */
    public static function create(int $bytes = self::BYTES): string
    {
        if ($bytes < 16) {
            throw new NotAuthenticatedException('A token needs at least 16 bytes to be one');
        }

        return bin2hex(random_bytes($bytes));
    }

    /**
     * What to keep instead of the token.
     *
     * A token is a password somebody else chose, so what is stored is a hash of it — a
     * database somebody reads then holds nothing they can sign in with. SHA-256 rather than
     * a password hash, because a token is already unguessable and this is checked on every
     * request.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Whether the token is the one behind the hash, in a way that takes the same time
     * whatever the answer.
     */
    public static function verify(string $token, string $hash): bool
    {
        return hash_equals($hash, self::hash($token));
    }

    /**
     * Whether two secrets are the same, without saying how far along they stopped matching.
     */
    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }
}
