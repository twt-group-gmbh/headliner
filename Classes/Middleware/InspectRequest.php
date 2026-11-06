<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Domain\DateTimeFactory;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Handles the request the page module makes to render a page (see PageHtmlFetcher), which carries the attribute
 * "headliner.inspect":
 * - the headings are rendered with the record they come from;
 * - the page cache is neither read nor written, so the marked markup never reaches a visitor;
 * - a hidden page is rendered too, so the page module can show the structure of a page that is not published yet.
 *   Hidden content elements are not, a visitor would not see them either;
 * - with the attribute "headliner.inspect.time" the page is rendered at that time, so a page whose start time is still
 *   to come, or whose end time is over, can be shown too (the preview of the core does the same with ADMCMD_simTime);
 * - with the attribute "headliner.inspect.groups" (a list of frontend user group ids) the page is rendered as a user who
 *   is a member of these groups, so a page for logged-in users can be shown too (ADMCMD_simUser in the core);
 * - with the attribute "headliner.inspect.workspace" (a workspace id) the page is rendered in that workspace, so the
 *   page module shows the structure the backend user works on and not the live version.
 *
 * The attribute exists only inside the PHP process: a request from outside cannot set it, whatever headers it sends.
 * This middleware has to run before the page is resolved, which is where a hidden page, a page that is not published
 * and a page the visitor may not see is turned down, and after the frontend user is authenticated.
 */
final readonly class InspectRequest implements MiddlewareInterface
{
    public const ATTRIBUTE = 'headliner.inspect';

    public const TIME_ATTRIBUTE = 'headliner.inspect.time';

    public const GROUPS_ATTRIBUTE = 'headliner.inspect.groups';

    public const WORKSPACE_ATTRIBUTE = 'headliner.inspect.workspace';

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

        $time = $request->getAttribute(self::TIME_ATTRIBUTE);
        if (is_int($time) && $time > 0) {
            $this->simulateTime($time);
        }
        $workspace = $request->getAttribute(self::WORKSPACE_ATTRIBUTE);
        if (is_int($workspace) && $workspace > 0) {
            // The repositories of the frontend read this aspect to show the versions of a workspace instead of the live records
            $this->context->setAspect('workspace', new WorkspaceAspect($workspace));
        }
        $groups = $request->getAttribute(self::GROUPS_ATTRIBUTE);
        if (is_array($groups) && $groups !== [] && $groups === array_filter($groups, is_int(...))) {
            // The aspect holds the group ids the access to pages and content is checked against. This has to be done
            // after the frontend user was authenticated, which sets the aspect.
            $this->context->setAspect('frontend.user', new UserAspect(null, array_values($groups)));
        }

        return $handler->handle($request->withAttribute('frontend.cache.instruction', $cacheInstruction));
    }

    /**
     * The same as the preview of the core does: the date aspect, and the globals the start and end time restrictions read.
     */
    private function simulateTime(int $time): void
    {
        $GLOBALS['SIM_EXEC_TIME'] = $time;
        $GLOBALS['SIM_ACCESS_TIME'] = $time - $time % 60;
        $this->context->setAspect('date', new DateTimeAspect(DateTimeFactory::createFromTimestamp($time)));
    }
}
