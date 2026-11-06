<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional;

use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RequestHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Twtgroup\Headliner\Backend\RteHeadingEditor;
use Twtgroup\Headliner\Backend\StructureBox;
use Twtgroup\Headliner\Backend\TagField;
use Twtgroup\Headliner\Structure\HeadingExtractor;
use Twtgroup\Headliner\Structure\HeadingValidator;
use Twtgroup\Headliner\Structure\PageHtmlFetcher;
use Twtgroup\Headliner\Tests\Functional\Fixtures\TestingContextRequestHandler;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A site with the site sets of fluid_styled_content and of the extension, and a few headers to render.
 */
abstract class AbstractFrontendTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'workspaces', 'rte_ckeditor'];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    /** @var array<string, mixed> request-related globals as they were before the test, put back in tearDown() */
    private array $originalGlobals = [];

    protected function setUp(): void
    {
        parent::setUp();
        // The testing framework does not reset these; a request or user left behind by one test can break later ones.
        foreach (['TYPO3_REQUEST', 'BE_USER'] as $key) {
            if (array_key_exists($key, $GLOBALS)) {
                $this->originalGlobals[$key] = $GLOBALS[$key];
            }
        }
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Content.csv');
    }

    protected function tearDown(): void
    {
        foreach (['TYPO3_REQUEST', 'BE_USER'] as $key) {
            if (array_key_exists($key, $this->originalGlobals)) {
                $GLOBALS[$key] = $this->originalGlobals[$key];
            } else {
                unset($GLOBALS[$key]);
            }
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $settings Site settings
     * @param string|null $pageTypoScript The TypoScript of the page; by default the content elements and nothing around them
     */
    protected function writeSite(array $settings = [], ?string $pageTypoScript = null): void
    {
        $siteWriter = GeneralUtility::makeInstance(SiteWriter::class);
        // The instance is shared by the tests: nothing of an earlier test may stay behind
        if (is_dir(Environment::getConfigPath() . '/sites/test')) {
            $siteWriter->delete('test');
        }
        $siteWriter->createNewBasicSite('test', 1, 'https://website.local/', ['typo3/fluid-styled-content', 'twtgroup/headliner']);
        if ($settings !== []) {
            $siteWriter->writeSettings('test', $settings);
        }
        // the page itself; everything else comes from the site sets
        file_put_contents(Environment::getConfigPath() . '/sites/test/setup.typoscript', $pageTypoScript ?? "page = PAGE\npage.10 < styles.content.get\n");
        // the TypoScript and the rendered pages are cached
        GeneralUtility::makeInstance(CacheManager::class)->flushCaches();
    }

    protected function render(?InternalRequest $request = null): string
    {
        $response = $this->executeFrontendSubRequest($request ?? new InternalRequest('https://website.local/'));
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    /**
     * The box of the page module, with the real fetcher and the real request handler of the core, only wrapped for the
     * testing framework.
     */
    protected function structureBox(?string $pageHtml = null, ?EventDispatcherInterface $dispatcher = null): StructureBox
    {
        // with a page html the page is not rendered: the box gets what the ViewHelpers would have put there
        $handler = $pageHtml !== null
            ? new class ($pageHtml) implements RequestHandlerInterface {
                public function __construct(private readonly string $html) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new HtmlResponse($this->html);
                }
            }
        : new TestingContextRequestHandler($this->get(RequestHandler::class));

        return new StructureBox(
            $this->get(SiteFinder::class),
            new PageHtmlFetcher($handler),
            new HeadingExtractor(),
            new HeadingValidator(),
            $this->get(ViewFactoryInterface::class),
            $this->get(LocalizationRepository::class),
            $this->get(TcaSchemaFactory::class),
            $this->get(UriBuilder::class),
            $this->rteEditor(),
            new TagField($this->get(TcaSchemaFactory::class)),
            $dispatcher ?? $this->get(EventDispatcherInterface::class),
        );
    }

    protected function rteEditor(): RteHeadingEditor
    {
        return new RteHeadingEditor($this->get(TcaSchemaFactory::class), $this->get(Richtext::class), $this->get(SiteFinder::class));
    }

    /** A request as the backend gives it to the AJAX route of the box */
    protected function backendRequest(): ServerRequest
    {
        $request = (new ServerRequest('https://website.local/typo3/ajax/headliner/structure', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        return $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }
}
