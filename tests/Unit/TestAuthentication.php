<?php

declare(strict_types=1);

namespace Quillstack\Auth\Tests\Unit;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Quillstack\Auth\Exceptions\NotAuthenticatedException;
use Quillstack\Auth\Exceptions\NotAuthorisedException;
use Quillstack\Auth\Identity;
use Quillstack\Auth\Middleware\AuthenticationMiddleware;
use Quillstack\Auth\Tests\Mocks\Identities;
use Quillstack\Auth\Tests\Mocks\Reached;
use Quillstack\DI\Container;
use Quillstack\Router\Dispatcher;
use Quillstack\Router\Router;
use Quillstack\ServerRequest\Factory\ServerRequest\GivenServerRequestFromGlobalsFactory;
use Quillstack\ServerRequest\Factory\ServerRequest\ServerRequestFromGlobalsFactory;
use Quillstack\ServerRequest\ServerRequest;
use Quillstack\Stream\InputStream;
use Quillstack\Uri\Factory\UriFactory;
use Quillstack\UnitTests\AssertEqual;
use Quillstack\UnitTests\AssertExceptions;
use Quillstack\UnitTests\Types\AssertBoolean;

class TestAuthentication
{
    private const TOKEN = 'a-token-nobody-would-guess-000000000000000000000000000000';

    public function __construct(
        private AssertEqual $assertEqual,
        private AssertBoolean $assertBoolean,
        private AssertExceptions $assertExceptions
    ) {
        //
    }

    /**
     * @return array{middleware: AuthenticationMiddleware, request: ServerRequestInterface, reached: Reached}
     */
    private function pipeline(string $path = '/orders', ?string $header = null, ?callable $routes = null): array
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => $path,
            'SERVER_PROTOCOL' => '1.1',
        ];

        if ($header !== null) {
            $server['HTTP_AUTHORIZATION'] = $header;
        }

        $container = new Container([
            StreamInterface::class => InputStream::class,
            UriFactoryInterface::class => UriFactory::class,
            ServerRequestFromGlobalsFactory::class => ['server' => $server],
        ]);

        /** @var Router $router */
        $router = $container->get(Router::class);
        ($routes ?? static function (Router $router): void {
            $router->get('/open', 'OpenController');
            $router->get('/orders', 'OrdersController')->requireAuthentication();
            $router->get('/admin', 'AdminController')->requireAuthentication('admin');
        })($router);

        $identities = (new Identities())->add(
            self::TOKEN,
            new Identity(42, ['support'], ['email' => 'ada@example.com'])
        );

        return [
            'middleware' => new AuthenticationMiddleware($identities, $container->get(Dispatcher::class)),
            'request' => $container->get(GivenServerRequestFromGlobalsFactory::class)
                ->createGivenServerRequest(ServerRequest::class),
            'reached' => new Reached(),
        ];
    }

    /**
     * Nothing is guarded unless the route says so.
     */
    public function anOpenRouteIsReachedWithoutAnything()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] = $this->pipeline('/open');

        $middleware->process($request, $reached);

        $this->assertBoolean->isTrue($reached->request !== null);
        $this->assertBoolean->isTrue($reached->identity() === null);
    }

    /**
     * Whoever the request is from is worked out even where nothing asked, so an open route
     * can still know who is reading it.
     */
    public function whoTheRequestIsFromIsWorkedOutAnyway()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/open', 'Bearer ' . self::TOKEN);

        $middleware->process($request, $reached);

        $identity = $reached->identity();

        $this->assertBoolean->isTrue($identity instanceof Identity);
        $this->assertEqual->equal(42, $identity?->id);
        $this->assertEqual->equal('ada@example.com', $identity?->attribute('email'));
    }

    public function aGuardedRouteIsReachedWithAToken()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/orders', 'Bearer ' . self::TOKEN);

        $middleware->process($request, $reached);

        $this->assertEqual->equal(42, $reached->identity()?->id);
    }

    /**
     * No credentials at all: 401, and nothing behind the guard ever runs.
     */
    public function withoutCredentialsAGuardedRouteRefuses()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] = $this->pipeline('/orders');

        try {
            $middleware->process($request, $reached);
            $this->assertBoolean->isTrue(false);
        } catch (NotAuthenticatedException $exception) {
            $this->assertEqual->equal(401, $exception->getStatusCode());
        }

        $this->assertBoolean->isTrue($reached->request === null);
    }

    /**
     * A token standing for nobody is the same answer as no token: saying which of the two it
     * was tells whoever is guessing that they are close.
     */
    public function aTokenNobodyKnowsIsTheSameAnswerAsNone()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/orders', 'Bearer something-else-entirely-0000000000000000000000000');

        $this->assertExceptions->expect(NotAuthenticatedException::class);

        $middleware->process($request, $reached);
    }

    /**
     * Recognised, and not allowed: a different answer from not being recognised at all.
     * 401 says try again with credentials; 403 says do not bother.
     */
    public function recognisedButWithoutTheRoleIsRefusedDifferently()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/admin', 'Bearer ' . self::TOKEN);

        try {
            $middleware->process($request, $reached);
            $this->assertBoolean->isTrue(false);
        } catch (NotAuthorisedException $exception) {
            $this->assertEqual->equal(403, $exception->getStatusCode());
        }

        $this->assertBoolean->isTrue($reached->request === null);
    }

    public function theRightRoleReachesIt()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/admin', 'Bearer ' . self::TOKEN, static function (Router $router): void {
                $router->get('/admin', 'AdminController')->requireAuthentication('support');
            });

        $middleware->process($request, $reached);

        $this->assertEqual->equal(42, $reached->identity()?->id);
    }

    /**
     * Any one of the roles will do.
     */
    public function anyOneOfTheRolesWillDo()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/admin', 'Bearer ' . self::TOKEN, static function (Router $router): void {
                $router->get('/admin', 'AdminController')->requireAuthentication('admin', 'support');
            });

        $middleware->process($request, $reached);

        $this->assertBoolean->isTrue($reached->request !== null);
    }

    /**
     * The scheme is matched without regard to case, which is what RFC 7235 says of it.
     */
    public function theSchemeIsReadWithoutRegardToCase()
    {
        foreach (['Bearer', 'bearer', 'BEARER'] as $scheme) {
            ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
                $this->pipeline('/orders', "{$scheme} " . self::TOKEN);

            $middleware->process($request, $reached);

            $this->assertEqual->equal(42, $reached->identity()?->id);
        }
    }

    /**
     * A header which is not a bearer token is no credentials at all, rather than a token of
     * whatever was written.
     */
    public function anotherSchemeIsNotAToken()
    {
        foreach (['Basic ' . base64_encode('ada:secret'), 'Bearer', 'Bearer   ', self::TOKEN] as $header) {
            ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
                $this->pipeline('/orders', $header);

            $refused = false;

            try {
                $middleware->process($request, $reached);
            } catch (NotAuthenticatedException) {
                $refused = true;
            }

            $this->assertBoolean->isTrue($refused);
        }
    }

    /**
     * Nothing matched is nothing to guard: the 404 answers for itself rather than being
     * turned into a 401, which would say the page exists.
     */
    public function nothingMatchedIsNotTurnedIntoARefusal()
    {
        ['middleware' => $middleware, 'request' => $request, 'reached' => $reached] =
            $this->pipeline('/nowhere');

        $middleware->process($request, $reached);

        $this->assertBoolean->isTrue($reached->request !== null);
    }
}
