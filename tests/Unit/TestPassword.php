<?php

declare(strict_types=1);

namespace Quillstack\Auth\Tests\Unit;

use Quillstack\Auth\Password;
use Quillstack\UnitTests\AssertEqual;
use Quillstack\UnitTests\Types\AssertBoolean;

class TestPassword
{
    public function __construct(
        private AssertEqual $assertEqual,
        private AssertBoolean $assertBoolean
    ) {
        //
    }

    public function whatWasHashedVerifies()
    {
        $hash = Password::hash('correct horse battery staple');

        $this->assertBoolean->isTrue(Password::verify('correct horse battery staple', $hash));
        $this->assertBoolean->isFalse(Password::verify('anything else', $hash));
    }

    /**
     * The same password hashed twice gives two different hashes, because each carries its own
     * salt. Two identical rows in a table would say two people chose the same password.
     */
    public function thesamePasswordHashesDifferentlyEachTime()
    {
        $first = Password::hash('same');
        $second = Password::hash('same');

        $this->assertBoolean->isFalse($first === $second);
        $this->assertBoolean->isTrue(Password::verify('same', $first));
        $this->assertBoolean->isTrue(Password::verify('same', $second));
    }

    /**
     * The password itself is nowhere in what is stored.
     */
    public function theHashDoesNotHoldThePassword()
    {
        $this->assertBoolean->isFalse(str_contains(Password::hash('hunter2'), 'hunter2'));
    }

    public function aFreshHashDoesNotNeedRedoing()
    {
        $this->assertBoolean->isFalse(Password::needsRehash(Password::hash('anything')));
    }

    /**
     * One made with something weaker does — which is how a stored hash is brought up to date
     * at the one moment the password is known.
     */
    public function anOlderHashSaysItNeedsRedoing()
    {
        $weak = password_hash('anything', PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertBoolean->isTrue(Password::needsRehash($weak));
        $this->assertBoolean->isTrue(Password::verify('anything', $weak));
    }

    /**
     * Anything at all can be a password, including nothing and something enormous.
     */
    public function anythingCanBeAPassword()
    {
        foreach (['', ' ', 'ą😀ż', str_repeat('a', 72)] as $password) {
            $this->assertBoolean->isTrue(Password::verify($password, Password::hash($password)));
        }
    }

    public function nothingVerifiesAgainstNonsense()
    {
        $this->assertBoolean->isFalse(Password::verify('anything', 'not a hash'));
    }
}
