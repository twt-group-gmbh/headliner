<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Handles the request the page module makes to render a page (see PageHtmlFetcher), which carries the attribute
 * "headliner.inspect":
 * - the headings are rendered with the record they come from;
 * - the page cache is neither read nor written, so the marked markup never reaches a visitor;
 * - a hidden page is rendered too, so the page module can show the structure of a page that is not published yet.
 *   Hidden content elements are not, a visitor would not see them either.
 *
 * The attribute exists only inside the PHP process: a request from outside cannot set it, whatever headers it sends.
 * This middleware has to run before the page is resolved, which is where a hidden page is turned down.
 */
final readonly class InspectRequest implements MiddlewareInterface
{
    public const ATTRIBUTE = 'headliner.inspect';

    public function __construct(private Context $context) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute(self::ATTRIBUTE) !== true) {
            return $handler->handle($request);
        }

        $cacheInstruction = $request->getAttribute('frontend.cache.instruction') ?? new CacheInstruction();
        $cacheInstruction->disableCache('EXT:headliner: request of the page module');

        // The frontend application sets the default (no hidden pages) before the middleware stack runs
        $this->context->setAspect('visibility', $this->context->getAspect('visibility')->withIncludeHiddenPages(true));

        return $handler->handle($request->withAttribute('frontend.cache.instruction', $cacheInstruction));
    }
}
