<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The box with the heading structure, as the page module loads it.
 */
final class StructureBoxTest extends AbstractFrontendTestCase
{
    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->writeSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['EXEC_TIME'] = time();
    }

    protected function tearDown(): void
    {
        if ($this->originalLang === []) {
            unset($GLOBALS['LANG']);
        } else {
            $GLOBALS['LANG'] = $this->originalLang['LANG'];
        }
        parent::tearDown();
    }

    #[Test]
    public function aStandardPageOfASiteHasABox(): void
    {
        self::assertTrue($this->structureBox()->supports(1));
    }

    #[Test]
    public function aPageThatDoesNotExistHasNoBox(): void
    {
        self::assertFalse($this->structureBox()->supports(9999));
    }

    #[Test]
    public function drawsTheStructureOfThePageWithItsProblemsAndTheReturnUrl(): void
    {
        $html = $this->structureBox()->render(
            $this->backendRequest(),
            1,
            [0],
            '/typo3/module/web/layout?id=1'
        );

        self::assertStringContainsString('class="headliner card', $html);
        self::assertStringContainsString('data-headliner-edit="tt_content:1:header_tag"', $html);
        // the fixture has no h1
        self::assertStringContainsString('The page has no H1.', $html);
        // the edit forms return to the page module, not to the AJAX route that has drawn the box
        self::assertStringContainsString('returnUrl=/typo3/module/web/layout?id%3D1', $html);
        self::assertStringNotContainsString('headliner/structure', $html);
    }

    #[Test]
    public function aPageWithoutABoxGivesNothing(): void
    {
        $html = $this->structureBox()->render(
            $this->backendRequest(),
            9999,
            [0],
            ''
        );

        self::assertSame('', $html);
    }

    #[Test]
    public function aHeadingFromTheTextCanBeChangedInTheBoxWhereTheFieldSaysItIs(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);
        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '/typo3/module/web/layout?id=8');

        // the headings of the text: place 0 and 1 in the text of element 21, without div as a choice
        self::assertStringContainsString('data-headliner-rte="tt_content:21:bodytext:0"', $html);
        self::assertStringContainsString('data-headliner-rte="tt_content:21:bodytext:1"', $html);
        self::assertStringContainsString('data-headliner-text="Opening hours"', $html);
        // the select of the header offers every tag, the two selects of the text only what the editor offers (h2, h3)
        self::assertSame(1, substr_count($html, '<option value="div"'));
        self::assertSame(1, substr_count($html, '<option value="h4"'));
        self::assertSame(3, substr_count($html, '<option value="h3"'));
        // the header of the element has its own tag field, not the text
        self::assertStringContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
    }

    #[Test]
    public function aHeadingFromTheTextIsOnlyALabelForAUserWhoMayNotEditTheElement(): void
    {
        $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringNotContainsString('data-headliner-rte="tt_content:21:', $html);
        self::assertStringNotContainsString('<select', $html);
    }

    #[Test]
    public function aHeadingFromTheTextIsOnlyALabelWhileTheSiteSettingIsOff(): void
    {
        // the setting is off by default
        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringNotContainsString('data-headliner-rte="tt_content:21:', $html);
        // only the select of the header of the element is left
        self::assertSame(1, substr_count($html, '<select'));
    }

    #[Test]
    public function theHeadingsOfOneElementAreMarkedAsAGroup(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        // element 21: its header and the two headings of its text, one run of three rows
        self::assertSame(3, substr_count($html, 'data-headliner-group="21"'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-start'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-middle'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-end'));
    }

    #[Test]
    public function aHeadingThatIsAloneInItsElementHasNoGroup(): void
    {
        // page 1: the elements have one heading each, except the one with a subheader
        $html = $this->structureBox()->render($this->backendRequest(), 1, [0], '');

        self::assertSame(1, substr_count($html, 'headliner__item--group-start'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-end'));
        self::assertSame(0, substr_count($html, 'headliner__item--group-middle'));
    }
}
