<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Handles the request the page module makes to render a page (see PageHtmlFetcher), which carries the attribute
 * "headliner.inspect": the headings are then rendered with the record they come from. Such a request neither reads
 * nor writes the page cache, so the marked markup never reaches a visitor.
 *
 * The attribute exists only inside the PHP process: a request from outside cannot set it, whatever headers it sends.
 */
final readonly class InspectRequest implements MiddlewareInterface
{
    public const ATTRIBUTE = 'headliner.inspect';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute(self::ATTRIBUTE) !== true) {
            return $handler->handle($request);
        }

        $cacheInstruction = $request->getAttribute('frontend.cache.instruction') ?? new CacheInstruction();
        $cacheInstruction->disableCache('EXT:headliner: request of the page module');

        return $handler->handle($request->withAttribute('frontend.cache.instruction', $cacheInstruction));
    }
}
