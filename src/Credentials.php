<?php

declare(strict_types=1);

namespace Quillstack\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * What a request offers as proof of who it is from.
 */
final class Credentials
{
    /**
     * The token an `Authorization: Bearer …` header carries, or null where there is none.
     *
     * The scheme is matched without regard to case, which is what RFC 7235 says of it — and
     * what a client library will eventually rely on.
     */
    public static function bearerToken(ServerRequestInterface $request): ?string
    {
        $header = trim($request->getHeaderLine('Authorization'));

        if ($header === '') {
            return null;
        }

        $parts = explode(' ', $header, 2);

        if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') {
            return null;
        }

        $token = trim($parts[1]);

        return $token === '' ? null : $token;
    }
}
