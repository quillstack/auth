<?php

declare(strict_types=1);

namespace Quillstack\Auth\Tests\Mocks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Quillstack\Auth\Middleware\AuthenticationMiddleware;

/**
 * The end of the pipeline: says who got here, so a test can see what the middleware let past.
 */
class Reached implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new EmptyResponse();
    }

    public function identity(): ?object
    {
        return $this->request === null
            ? null
            : AuthenticationMiddleware::identityOf($this->request);
    }
}
