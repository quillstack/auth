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

Where the users are kept, and what a user is, belong to the application. What belongs here is
the part which is easy to get subtly wrong: comparing secrets in constant time, hashing what
is stored, and making sure a rule written once is applied everywhere.

### Requirements

- PHP 8.1 or newer

### Installation

```shell
composer require quillstack/auth
```

### Usage

#### Saying which routes are guarded

```php
$router->get('/orders', OrdersController::class)->requireAuthentication();
$router->delete('/orders/:id', DeleteOrderController::class)->requireAuthentication('admin');
```

The route is the one place that decides, and the middleware is the one place that enforces.
A rule kept in each controller instead is a rule which is one day not kept — and the day it
is not kept, nothing says so.

Nothing is guarded unless it says so, and any one of the roles named will do.

#### Saying who somebody is

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

#### Reading who it was

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

### What is refused, and how

| Answer | Means |
| --- | --- |
| `401` `NotAuthenticatedException` | nobody was recognised: no credentials, or credentials standing for nobody |
| `403` `NotAuthorisedException` | somebody was recognised, and may not do this |

They are different on purpose: `401` says try again with credentials, `403` says do not
bother. No token and a token nobody knows are the same answer, because saying which of the
two it was tells whoever is guessing that they are close.

A request which matched no route is not turned into a refusal — a `404` becoming a `401`
would say the page exists.

### Passwords

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

### Tokens

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

### Technical documentation

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

### What this is not

There are no sessions, no cookies and no login form: this is for APIs, where the client holds
a token. There is no permission language either — a role is a string, and anything finer is a
question for the application, which knows what it is about.

### Unit tests

```shell
composer test
composer test:coverage
composer stan
```

### License

MIT. See [LICENSE](LICENSE).
