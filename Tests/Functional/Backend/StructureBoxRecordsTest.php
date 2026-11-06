<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Headings in the records of a collection (the cards of a content element), as the box finds their fields: from the
 * marks `headliner:headline.children` puts around a record. The table of the cards is a fixture extension; the page
 * html is what the ViewHelpers would have put on the page.
 */
final class StructureBoxRecordsTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    private const TABLE = 'tx_headlinertestcards_card';

    /**
     * Element 21 of page 8 with three cards: a title in the tag field, a heading in the rich text, a heading that is
     * neither, two cards whose tag fields cannot be told apart, and two equal headings in one text.
     */
    private const PAGE = '<html><body><main>'
        . '<h2 data-headliner-edit="tt_content:21:header_tag" data-headliner-element="21">Intro</h2>'
        . '<div data-headliner-record="tx_headlinertestcards_card:1" data-headliner-element="21">'
        . '<h3>First card</h3><h3>Card text heading</h3><h5>Nowhere</h5></div>'
        . '<div data-headliner-record="tx_headlinertestcards_card:2" data-headliner-element="21"><h3>Second card</h3></div>'
        . '<div data-headliner-record="tx_headlinertestcards_card:3" data-headliner-element="21">'
        . '<h2>Third card</h2><h3>Same</h3><h3>Same</h3></div>'
        . '</main></body></html>';

    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Cards.csv');
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);
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

    private function renderBox(): string
    {
        return $this->structureBox(self::PAGE)->render($this->backendRequest(), 8, [0], '');
    }

    /**
     * The row of the list with a heading of that text.
     */
    private function row(string $html, string $text): string
    {
        preg_match_all('#<li\b.*?</li>#s', $html, $matches);
        $rows = array_values(array_filter(
            $matches[0],
            static fn(string $row): bool => str_contains((string) preg_replace('/\s+/', ' ', strip_tags($row)), $text)
        ));
        self::assertCount(1, $rows, 'the row of "' . $text . '"');

        return $rows[0];
    }

    #[Test]
    public function aTitleIsChangedInTheTagFieldOfItsCard(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderBox();

        $row = $this->row($html, 'First card');
        self::assertStringContainsString('data-headliner-edit="' . self::TABLE . ':1:tag"', $row);
        // the values of the field, nothing else
        self::assertSame(['h2', 'h3', 'h4', 'div'], $this->optionValues($row));
        // the badge is the title of the table
        self::assertStringContainsString('Test card', $row);
    }

    #[Test]
    public function aHeadingInTheRichTextOfACardIsChangedInThatText(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderBox();

        $row = $this->row($html, 'Card text heading');
        self::assertStringContainsString('data-headliner-rte="' . self::TABLE . ':1:text:0"', $row);
        // what the editor of the text offers (the default preset: h2 and h3)
        self::assertSame(['h2', 'h3'], $this->optionValues($row));
        self::assertMatchesRegularExpression('#<span class="badge badge-secondary">\s*RTE\s*</span>#', $row);
    }

    #[Test]
    public function equalHeadingsInOneTextAreToldApartByTheirPlace(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderBox();

        self::assertStringContainsString('data-headliner-rte="' . self::TABLE . ':3:text:0"', $html);
        self::assertStringContainsString('data-headliner-rte="' . self::TABLE . ':3:text:1"', $html);
        // the title of that card is the tag field with h2, not a heading of the text
        self::assertStringContainsString('data-headliner-edit="' . self::TABLE . ':3:tag"', $this->row($html, 'Third card'));
    }

    #[Test]
    public function aHeadingThatIsInNoFieldOfTheRecordIsOnlyALabel(): void
    {
        $this->setUpBackendUser(1);

        $row = $this->row($this->renderBox(), 'Nowhere');

        self::assertStringNotContainsString('<select', $row);
    }

    #[Test]
    public function aHeadingWhoseTagFieldCannotBeToldApartIsOnlyALabel(): void
    {
        $this->setUpBackendUser(1);

        // card 2 has h3 in both of its tag fields: which one the heading was rendered with is not known
        $row = $this->row($this->renderBox(), 'Second card');

        self::assertStringNotContainsString('<select', $row);
    }

    #[Test]
    public function theHeadingsOfOneElementAndItsCardsAreOneGroup(): void
    {
        $this->setUpBackendUser(1);

        // the header of the element, three cards and what is in their texts: eight headings under the element
        self::assertSame(8, substr_count($this->renderBox(), 'data-headliner-group="21"'));
    }

    #[Test]
    public function theRichTextOfACardIsOnlyChangedWhileTheSiteSettingIsOn(): void
    {
        $this->setUpBackendUser(1);
        $this->writeSite(['headliner' => ['editRteHeadings' => false]]);

        $html = $this->renderBox();

        self::assertStringNotContainsString('data-headliner-rte="' . self::TABLE, $html);
        // the tag fields are not affected
        self::assertStringContainsString('data-headliner-edit="' . self::TABLE . ':1:tag"', $html);
    }

    #[Test]
    public function aUserWithoutTheRightsForTheTableSeesOnlyLabels(): void
    {
        // this editor may not modify any table
        $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');

        $html = $this->renderBox();

        self::assertStringNotContainsString('<select', $html);
        self::assertStringNotContainsString('data-headliner-rte=', $html);
    }

    /**
     * @return list<string>
     */
    private function optionValues(string $row): array
    {
        preg_match_all('#<option value="([^"]+)"#', $row, $matches);

        return $matches[1];
    }
}
