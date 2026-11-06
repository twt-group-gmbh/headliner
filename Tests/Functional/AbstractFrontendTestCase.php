<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A site with the site sets of fluid_styled_content and of the extension, and a few headers to render.
 */
abstract class AbstractFrontendTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'workspaces'];

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
}
