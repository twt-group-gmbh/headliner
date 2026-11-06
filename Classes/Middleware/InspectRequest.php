<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Twtgroup\Headliner\Inspection\InspectToken;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Recognises the request of the page module (it carries the inspect token) and marks it with the attribute
 * "headliner.inspect", so the headings are rendered with the record they come from. Such a request neither
 * reads nor writes the page cache, so the marked markup never reaches a visitor.
 */
final readonly class InspectRequest implements MiddlewareInterface
{
    public const ATTRIBUTE = 'headliner.inspect';

    public function __construct(private InspectToken $token) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->token->isValid($request->getHeaderLine(InspectToken::HEADER))) {
            return $handler->handle($request);
        }

        $cacheInstruction = $request->getAttribute('frontend.cache.instruction') ?? new CacheInstruction();
        $cacheInstruction->disableCache('EXT:headliner: request of the page module');

        return $handler->handle(
            $request
                ->withAttribute('frontend.cache.instruction', $cacheInstruction)
                ->withAttribute(self::ATTRIBUTE, true)
        );
    }
}
