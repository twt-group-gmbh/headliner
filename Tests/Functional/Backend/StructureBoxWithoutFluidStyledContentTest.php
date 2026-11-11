<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The box on a site without fluid_styled_content: the base set only, a page of its own template, the real frontend
 * rendering behind it.
 */
final class StructureBoxWithoutFluidStyledContentTest extends AbstractFrontendTestCase
{
    protected array $coreExtensionsToLoad = ['rte_ckeditor', 'workspaces'];

    private const PAGE = "page = PAGE\npage.10 = FLUIDTEMPLATE\npage.10.template = TEXT\npage.10.template.value (\n"
        . '<html xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers" data-namespace-typo3-fluid="true">'
        . '<headliner:headline.values tag="h1" edit="tt_content:1:header_tag">The page</headliner:headline.values>'
        . '<headliner:headline.values tag="h4">Skips two levels</headliner:headline.values></html>'
        . "\n)\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->writeSite([], self::PAGE, ['twtgroup/headliner']);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['EXEC_TIME'] = time();
    }

    #[Test]
    public function theBoxListsTheHeadingsAndFindsTheProblemsOfAPageWithoutFluidStyledContent(): void
    {
        $this->setUpBackendUser(1);

        $html = $this->structureBox()->render($this->backendRequest(), 1, [0], '');

        self::assertStringContainsString('The page', $html);
        self::assertStringContainsString('Skips two levels', $html);
        // the skipped level is found like on any page
        self::assertStringContainsString('headliner__item--issue', $html);
    }

    #[Test]
    public function theTagOfAMarkedHeadingCanBeChangedInTheBoxWithoutFluidStyledContent(): void
    {
        $this->setUpBackendUser(1);

        $html = $this->structureBox()->render($this->backendRequest(), 1, [0], '');

        self::assertStringContainsString('data-headliner-edit="tt_content:1:header_tag"', $html);
        self::assertStringContainsString('<select', $html);
    }
}
