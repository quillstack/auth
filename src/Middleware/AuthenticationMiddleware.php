<?php

declare(strict_types=1);

namespace Quillstack\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Quillstack\Auth\Credentials;
use Quillstack\Auth\Exceptions\NotAuthenticatedException;
use Quillstack\Auth\Exceptions\NotAuthorisedException;
use Quillstack\Auth\Identity;
use Quillstack\Auth\IdentityProviderInterface;
use Quillstack\Router\Dispatcher;
use Quillstack\Router\GuardedRouteInterface;

/**
 * Works out who the request is from, and refuses it where the route it matched says only
 * somebody may reach it.
 *
 * The route is the one place that decides, and this is the one place that enforces. A rule
 * kept in each controller instead is a rule which is one day not kept.
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * The request attribute the identity travels on. Prefixed, because route parameters
     * become attributes too and an application names those.
     */
    public const IDENTITY = 'quillstack.identity';

    public function __construct(
        private readonly IdentityProviderInterface $identities,
        private readonly Dispatcher $dispatcher
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->identify($request);

        if ($identity !== null) {
            $request = $request->withAttribute(self::IDENTITY, $identity);
        }

        $route = $this->dispatcher->dispatch($request);

        // A route which does not say is a route nobody guards.
        if (!$route instanceof GuardedRouteInterface || !$route->requiresAuthentication()) {
            return $handler->handle($request);
        }

        if ($identity === null) {
            throw new NotAuthenticatedException('Not authenticated');
        }

        $roles = $route->getRequiredRoles();

        if ($roles !== [] && !$identity->hasAnyRole($roles)) {
            throw new NotAuthorisedException('Not allowed');
        }

        return $handler->handle($request);
    }

    /**
     * Whoever the request is from, or nobody.
     *
     * A request with no token and a request with a token nobody knows are the same answer:
     * the difference is only reported where the route asked for one.
     */
    private function identify(ServerRequestInterface $request): ?Identity
    {
        $token = Credentials::bearerToken($request);

        return $token === null ? null : $this->identities->findByToken($token);
    }

    /**
     * Whoever a request is from, for anything holding the request rather than the middleware.
     */
    public static function identityOf(ServerRequestInterface $request): ?Identity
    {
        $identity = $request->getAttribute(self::IDENTITY);

        return $identity instanceof Identity ? $identity : null;
    }
}
