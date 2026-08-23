<?php

declare(strict_types=1);

namespace Quillstack\Auth\Tests\Mocks;

use Quillstack\Auth\Identity;
use Quillstack\Auth\IdentityProviderInterface;
use Quillstack\Auth\Token;

/**
 * Tokens kept the way they should be: hashed, so what is stored is not what signs in.
 */
class Identities implements IdentityProviderInterface
{
    /**
     * @var array<string, Identity>
     */
    private array $byHash = [];

    public function add(string $token, Identity $identity): self
    {
        $this->byHash[Token::hash($token)] = $identity;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function findByToken(string $token): ?Identity
    {
        return $this->byHash[Token::hash($token)] ?? null;
    }
}
