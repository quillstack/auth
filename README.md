# Quillstack Auth

[![Tests](https://github.com/quillstack/auth/actions/workflows/tests.yml/badge.svg)](https://github.com/quillstack/auth/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/quillstack/auth.svg)](https://packagist.org/packages/quillstack/auth)
[![Downloads](https://img.shields.io/packagist/dt/quillstack/auth.svg)](https://packagist.org/packages/quillstack/auth)
[![PHP Version](https://img.shields.io/packagist/php-v/quillstack/auth)](https://packagist.org/packages/quillstack/auth)
[![StyleCI](https://github.styleci.io/repos/1343670965/shield?branch=main)](https://github.styleci.io/repos/1343670965?branch=main)
[![CodeFactor](https://www.codefactor.io/repository/github/quillstack/auth/badge)](https://www.codefactor.io/repository/github/quillstack/auth)
[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=quillstack_auth&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=quillstack_auth)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=quillstack_auth&metric=coverage)](https://sonarcloud.io/summary/new_code?id=quillstack_auth)
[![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=quillstack_auth&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=quillstack_auth)
[![Reliability](https://sonarcloud.io/api/project_badges/measure?project=quillstack_auth&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=quillstack_auth)
[![Security](https://sonarcloud.io/api/project_badges/measure?project=quillstack_auth&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=quillstack_auth)
[![License](https://img.shields.io/packagist/l/quillstack/auth)](https://github.com/quillstack/auth/blob/main/LICENSE)

Authentication for APIs: a route says what reaching it requires, and one place enforces it.
Full documentation: https://quillstack.org/auth

## Why this exists

Where the users are kept, and what a user is, belong to the application. What belongs here is
the part which is easy to get subtly wrong: comparing secrets in constant time, hashing what
is stored, and making sure a rule written once is applied everywhere.

Authentication is a thing you get right once and then have to keep right. The failures are
quiet ones — a comparison that leaks how close a guess was, a hash written straight to a
column, a rule enforced in nine controllers and forgotten in the tenth. None of them announce
themselves, and all of them are the sort of thing a route declaration and one middleware make
structurally hard to get wrong.

## Requirements

- PHP 8.1 or newer

## Installation

```shell
composer require quillstack/auth
```

## Usage

### Saying which routes are guarded

```php
$router->get('/orders', OrdersController::class)->requireAuthentication();
$router->delete('/orders/:id', DeleteOrderController::class)->requireAuthentication('admin');
```

The route is the one place that decides, and the middleware is the one place that enforces.
A rule kept in each controller instead is a rule which is one day not kept — and the day it
is not kept, nothing says so.

Nothing is guarded unless it says so, and any one of the roles named will do.

### Saying who somebody is

The application owns this, because only it knows where the tokens are:

```php
use Quillstack\Auth\Identity;
use Quillstack\Auth\IdentityProviderInterface;
use Quillstack\Auth\Token;

final class Users implements IdentityProviderInterface
{
    public function __construct(private readonly Orm $orm)
    {
    }

    public function findByToken(string $token): ?Identity
    {
        $tokens = $this->orm->repository(ApiToken::class);
        $found = $tokens->one($tokens->query()->where('hash', '=', Token::hash($token)));

        return $found === null
            ? null
            : new Identity($found->userId, $found->roles, ['email' => $found->email]);
    }
}
```

Point the framework at it and the middleware does the rest:

```php
$app = new App(__DIR__ . '/../.env', [
    IdentityProviderInterface::class => Users::class,
]);
```

### Reading who it was

```php
use Quillstack\Auth\Middleware\AuthenticationMiddleware;

public function handle(ServerRequestInterface $request): OrdersResponse
{
    $identity = AuthenticationMiddleware::identityOf($request);

    $identity?->id;
    $identity?->hasRole('admin');
    $identity?->attribute('email');
}
```

It is worked out for every request, guarded or not — so an open route can still know who is
reading it.

## What is refused, and how

| Answer | Means |
| --- | --- |
| `401` `NotAuthenticatedException` | nobody was recognised: no credentials, or credentials standing for nobody |
| `403` `NotAuthorisedException` | somebody was recognised, and may not do this |

They are different on purpose: `401` says try again with credentials, `403` says do not
bother. No token and a token nobody knows are the same answer, because saying which of the
two it was tells whoever is guessing that they are close.

A request which matched no route is not turned into a refusal — a `404` becoming a `401`
would say the page exists.

## Passwords

```php
use Quillstack\Auth\Password;

$user->password = Password::hash($given);

if (Password::verify($given, $user->password)) {
    // …
}
```

The algorithm is whatever PHP currently considers best, and it changes when PHP does. The
same password hashed twice gives two different hashes, because each carries its own salt —
two identical rows in a table would say two people chose the same password.

Somebody signing in is the one moment their password is known, so it is the only moment an
old hash can be brought up to date:

```php
if (Password::verify($given, $user->password) && Password::needsRehash($user->password)) {
    $user->password = Password::hash($given);
}
```

## Tokens

```php
use Quillstack\Auth\Token;

$token = Token::create();          // hand this to the client, once
$stored = Token::hash($token);     // keep this

Token::verify($token, $stored);
```

Two things go wrong with tokens written by hand: they are made from something guessable, and
they are compared with `===`, which stops at the first byte that differs and so says how much
of a guess was right. `Token::create()` takes its randomness from the operating system, and
everything here compares with `hash_equals()`.

A token is a password somebody else chose, so what is stored is a hash of it: a database
somebody reads then holds nothing they can sign in with.

## Technical documentation

| Class | What it is |
| --- | --- |
| `Identity` | who a request is from: an id, roles, and whatever else the application carries |
| `IdentityProviderInterface` | `findByToken(string $token): ?Identity` — the one thing the application writes |
| `Middleware\AuthenticationMiddleware` | works out who, and enforces what the route asked for |
| `Credentials` | reads the `Authorization: Bearer …` header, without regard to the scheme's case |
| `Password` | `hash()`, `verify()`, `needsRehash()` |
| `Token` | `create()`, `hash()`, `verify()`, `equals()` |
| `Exceptions\AuthException` | what everything here extends; carries the status it means |

The identity travels on the request under `AuthenticationMiddleware::IDENTITY`, prefixed
because route parameters become attributes too.

## What this is not

There are no sessions, no cookies and no login form: this is for APIs, where the client holds
a token. There is no permission language either — a role is a string, and anything finer is a
question for the application, which knows what it is about.

## Benchmark

Verifying a credential is the one thing here that happens on every single request, so it is the
thing worth measuring. The comparison is against the two JWT libraries most PHP APIs reach for.

Read the result with its caveat first: **these are not doing the same amount of work.** A JWT
carries its own claims, so verifying one answers *who is this* with no database involved. A
stored token answers only *is this the token* — the application still has to fetch the row it
belongs to, and that lookup costs far more than any number below. This measures the
cryptographic step, not the cost of a request.

100,000 verifications, interleaved runs, median of five, PHP 8.4 on an M-series Mac:

| Package | Version | Per verification | |
| --- | --- | --- | --- |
| **quillstack/auth** | 0.8.0 | **0.53 µs** | SHA-256, then `hash_equals()` |
| firebase/php-jwt | 7.1.0 | 2.91 µs | HMAC, base64, JSON decode |
| lcobucci/jwt | 5.6.0 | 2.88 µs | as above, through a parser and a validator |

What is loaded to do it, which does not vary by machine:

| Package | Files loaded | Memory | On disk |
| --- | --- | --- | --- |
| **quillstack/auth** | **1** | **81 KB** | 112 KB |
| firebase/php-jwt | 2 | 177 KB | 116 KB |
| lcobucci/jwt | 28 | 198 KB | 312 KB |

So: cheaper per check, and it loads less to do it. That follows from doing less — hashing a
string is cheaper than parsing a token, and it has to be, because unlike a JWT it is not the
whole answer. Choose a JWT when you want a credential that needs no lookup and can be verified
by a service that cannot reach your database. Choose this when you want a credential you can
revoke, which a JWT cannot be without the lookup you were avoiding.

`Password` is not benchmarked. It calls PHP's `password_hash()`, as every honest
implementation does, and that function is deliberately slow — measuring it would compare PHP
to itself.

One thing the comparison turned up: `firebase/php-jwt` below 7.0.0 carries a
[security advisory](https://packagist.org/security-advisories/), and Composer refuses to
install it. If you are on `^6`, that is worth knowing.

## Tests

```shell
composer test
composer test:coverage
```

### Static analysis

```shell
composer stan
```

## The rest of Quillstack

This is one component of [Quillstack](https://github.com/quillstack), a PHP framework which is
as simple to use as it is strict about what it does.

- [quillstack/router](https://github.com/quillstack/router) — where a route says what it requires
- [quillstack/framework](https://github.com/quillstack/framework) — where the middleware is wired in
- [quillstack/orm](https://github.com/quillstack/orm) — one way to keep the tokens
- [quillstack/http-status](https://github.com/quillstack/http-status) — the codes these refusals carry

## License

MIT — see [LICENSE](https://github.com/quillstack/auth/blob/main/LICENSE).
