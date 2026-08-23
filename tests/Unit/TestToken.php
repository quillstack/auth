<?php

declare(strict_types=1);

namespace Quillstack\Auth\Tests\Unit;

use Quillstack\Auth\Exceptions\NotAuthenticatedException;
use Quillstack\Auth\Token;
use Quillstack\UnitTests\AssertEqual;
use Quillstack\UnitTests\AssertExceptions;
use Quillstack\UnitTests\Types\AssertBoolean;

class TestToken
{
    public function __construct(
        private AssertEqual $assertEqual,
        private AssertBoolean $assertBoolean,
        private AssertExceptions $assertExceptions
    ) {
        //
    }

    public function oneIsAsLongAsItSaid()
    {
        $this->assertEqual->equal(64, strlen(Token::create()));
        $this->assertEqual->equal(32, strlen(Token::create(16)));
    }

    /**
     * No two the same, which is the whole point of one.
     */
    public function noTwoAreTheSame()
    {
        $tokens = [];

        foreach (range(1, 200) as $i) {
            $tokens[] = Token::create();
        }

        $this->assertEqual->equal(200, count(array_unique($tokens)));
    }

    /**
     * Something short enough to be guessed is refused, rather than made.
     */
    public function tooShortToBeATokenIsRefused()
    {
        $this->assertExceptions->expect(NotAuthenticatedException::class);

        Token::create(8);
    }

    /**
     * What is kept is not what signs in, so a database somebody reads holds nothing usable.
     */
    public function whatIsStoredIsNotTheToken()
    {
        $token = Token::create();
        $stored = Token::hash($token);

        $this->assertBoolean->isFalse($stored === $token);
        $this->assertBoolean->isFalse(str_contains($stored, $token));
        $this->assertBoolean->isTrue(Token::verify($token, $stored));
        $this->assertBoolean->isFalse(Token::verify(Token::create(), $stored));
    }

    public function hashingIsTheSameAnswerEveryTime()
    {
        $token = Token::create();

        $this->assertEqual->equal(Token::hash($token), Token::hash($token));
    }

    public function twoSecretsAreCompared()
    {
        $this->assertBoolean->isTrue(Token::equals('abc', 'abc'));
        $this->assertBoolean->isFalse(Token::equals('abc', 'abd'));
        $this->assertBoolean->isFalse(Token::equals('abc', 'ab'));
    }
}
