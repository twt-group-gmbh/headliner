<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\RequestHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Renders the frontend of a page inside the running PHP process, so headings from content elements, RTE content,
 * other extensions and backend layouts are included without special configuration. There is no HTTP request: a
 * firewall, Basic Auth, TLS settings or a CDN in front of the site do not matter, and nothing waits for a timeout.
 *
 * Dispatching the sub-request runs the frontend middleware stack, which changes state the surrounding backend
 * request still relies on. It is put back afterwards.
 */
final readonly class PageHtmlFetcher
{
    /** The aspects of the shared Context the frontend resets */
    private const RESTORED_CONTEXT_ASPECTS = ['date', 'visibility', 'backend.user', 'frontend.user', 'workspace', 'language'];

    public function __construct(
        #[Autowire(service: RequestHandler::class)]
        private RequestHandlerInterface $requestHandler,
    ) {}

    /**
     * @param Simulation|null $simulation Time, frontend user groups and workspace to render the page with
     * @return string|null The HTML, NULL if the page could not be rendered
     */
    public function fetch(UriInterface $uri, ?Simulation $simulation = null): ?string
    {
        // - $GLOBALS['BE_USER'] becomes null (the sub-request carries no backend cookie), which breaks session based
        //   flash messages afterwards;
        // - $GLOBALS['TYPO3_REQUEST'] and $GLOBALS['LANG'] are replaced by those of the sub-request;
        // - the "backend.user" aspect of the shared Context is reset to "not logged in": the backend middleware looks at
        //   exactly that aspect when it builds the response and, finding no user, deletes the session cookie - the editor
        //   would be logged out;
        // - the frontend rendering resets the shared PageRenderer (CSS, JavaScript, title), so the backend page would
        //   lose its stylesheets and scripts.
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $originalRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $languageService = $GLOBALS['LANG'] ?? null;
        // the simulated time of the frontend: set only if there was none, so what was not there is removed again
        $simulatedTimes = array_intersect_key($GLOBALS, array_flip(['SIM_EXEC_TIME', 'SIM_ACCESS_TIME']));
        $pageRenderer = $this->getPageRenderer();
        $pageRendererState = $pageRenderer !== null ? clone $pageRenderer : null;
        $context = GeneralUtility::makeInstance(Context::class);
        $aspects = [];
        foreach (self::RESTORED_CONTEXT_ASPECTS as $name) {
            if ($context->hasAspect($name)) {
                $aspects[$name] = $context->getAspect($name);
            }
        }

        try {
            $request = GeneralUtility::makeInstance(ServerRequest::class)
                ->withQueryParams([])
                ->withUri($uri)
                ->withMethod('GET')
                ->withAttribute(InspectRequest::ATTRIBUTE, true)
                ->withAttribute(InspectRequest::TIME_ATTRIBUTE, $simulation?->time)
                ->withAttribute(InspectRequest::GROUPS_ATTRIBUTE, $simulation?->groups)
                ->withAttribute(InspectRequest::WORKSPACE_ATTRIBUTE, $simulation?->workspace);
            $response = $this->requestHandler->handle($request);

            return $response->getStatusCode() === 200 ? (string) $response->getBody() : null;
        } catch (\Throwable) {
            // A site that cannot render (for example no TypoScript: "No page configured for type=0") throws instead of
            // answering; that is a page that cannot be shown, not a reason to break the page module.
            return null;
        } finally {
            $GLOBALS['BE_USER'] = $backendUser;
            if ($originalRequest !== null) {
                $GLOBALS['TYPO3_REQUEST'] = $originalRequest;
            }
            if ($languageService !== null) {
                $GLOBALS['LANG'] = $languageService;
            }
            foreach (['SIM_EXEC_TIME', 'SIM_ACCESS_TIME'] as $key) {
                if (array_key_exists($key, $simulatedTimes)) {
                    $GLOBALS[$key] = $simulatedTimes[$key];
                } else {
                    unset($GLOBALS[$key]);
                }
            }
            foreach ($aspects as $name => $aspect) {
                $context->setAspect($name, $aspect);
            }
            if ($pageRenderer !== null && $pageRendererState !== null) {
                $this->restoreState($pageRenderer, $pageRendererState);
            }
        }
    }

    private function getPageRenderer(): ?PageRenderer
    {
        try {
            return GeneralUtility::makeInstance(PageRenderer::class);
        } catch (\Throwable) {
            // Without an application type (as in a unit test) there is no backend page that could lose its assets.
            return null;
        }
    }

    /**
     * Puts the state of $snapshot (a clone taken earlier) back into $object, keeping the instance itself: others
     * - the module template - hold a reference to it, so replacing the singleton would not help.
     */
    private function restoreState(object $object, object $snapshot): void
    {
        (function (object $snapshot): void {
            foreach (get_object_vars($snapshot) as $name => $value) {
                try {
                    $this->$name = $value;
                } catch (\Error) {
                    // a readonly property cannot be set again - and was not changed either
                }
            }
        })->call($object, $snapshot);
    }
}
