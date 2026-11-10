<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Without fluid_styled_content: no core extension is loaded here. The set is valid and reports nothing, and the
 * ViewHelper renders in a template of its own.
 */
final class SiteSetsWithoutFluidStyledContentTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/RootPage.csv');
    }

    #[Test]
    public function theSetIsValidWithoutFluidStyledContentAndNothingIsReported(): void
    {
        $registry = $this->get(SetRegistry::class);

        self::assertNotNull($registry->getSet('twtgroup/headliner'));
        // no invalid set means no error in the log: the optional dependency is skipped without one
        self::assertSame([], $registry->getInvalidSets());
    }

    #[Test]
    public function aSiteWithTheBaseSetRendersTheViewHelperAndMarksItForThePageModule(): void
    {
        $siteWriter = GeneralUtility::makeInstance(SiteWriter::class);
        $siteWriter->createNewBasicSite('test', 1, 'https://website.local/', ['twtgroup/headliner']);
        file_put_contents(
            Environment::getConfigPath() . '/sites/test/setup.typoscript',
            "page = PAGE\npage.10 = FLUIDTEMPLATE\npage.10.template = TEXT\npage.10.template.value (\n"
            . '<html xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers" data-namespace-typo3-fluid="true">'
            . '<headliner:headline tag="h3" edit="tt_content:7:header_tag">Own template</headliner:headline></html>'
            . "\n)\n"
        );
        GeneralUtility::makeInstance(CacheManager::class)->flushCaches();

        $visitor = (string) $this->executeFrontendSubRequest(new InternalRequest('https://website.local/'))->getBody();
        $inspected = (string) $this->executeFrontendSubRequest(
            (new InternalRequest('https://website.local/'))->withAttribute(InspectRequest::ATTRIBUTE, true)
        )->getBody();

        self::assertStringContainsString('<h3>Own template</h3>', $visitor);
        self::assertStringContainsString('<h3 data-headliner-edit="tt_content:7:header_tag">Own template</h3>', $inspected);
    }
}
