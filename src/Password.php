<?php

declare(strict_types=1);

namespace Quillstack\Auth;

/**
 * Hashing a password, and checking one.
 *
 * PHP has done this correctly for years; what it has not done is stop people writing
 * `md5($password)` or comparing with `==`. This is the correct way with nothing to decide:
 * the algorithm is whatever PHP currently considers best, and it changes when PHP does.
 */
final class Password
{
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Whether the password is the one behind the hash.
     *
     * `password_verify()` takes the same time whether the answer is yes or no, which is why
     * it is used rather than any comparison of the two.
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Whether the hash was made with something weaker than what would be used now — after
     * PHP changed its default, or after the cost was raised.
     *
     * Somebody signing in is the one moment their password is known, so it is the only moment
     * a hash can be brought up to date.
     *
     * ```php
     * if (Password::verify($given, $user->password) && Password::needsRehash($user->password)) {
     *     $user->password = Password::hash($given);
     * }
     * ```
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
