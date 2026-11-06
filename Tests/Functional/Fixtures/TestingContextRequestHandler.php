<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * The testing framework puts a middleware into the frontend that needs the request attribute "typo3.testing.context",
 * which only its own way of requesting a page sets. This handler adds it and leaves the rest to the real handler.
 */
final readonly class TestingContextRequestHandler implements RequestHandlerInterface
{
    public function __construct(private RequestHandlerInterface $inner) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inner->handle($request->withAttribute('typo3.testing.context', new InternalRequestContext()));
    }
}
