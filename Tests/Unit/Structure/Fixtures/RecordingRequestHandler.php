<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Stands in for the frontend: remembers the request it got and lets the test decide what happens.
 */
final class RecordingRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $received = null;

    /**
     * @param \Closure(ServerRequestInterface): ResponseInterface $callback
     */
    public function __construct(private readonly \Closure $callback) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->received = $request;

        return ($this->callback)($request);
    }
}
