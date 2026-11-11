<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * headliner:headline.row, a row as an array: a record is made of it, and the heading is the one of a record.
 */
final class HeadlineRowTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Cards.csv');
        $this->getConnectionPool()->getConnectionForTable('tx_headlinertestcards_single')
            ->insert('tx_headlinertestcards_single', ['uid' => 5, 'pid' => 8, 'title' => 'Single', 'level' => 'h4']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $table, int $uid): array
    {
        $row = $this->getConnectionPool()->getConnectionForTable($table)->select(['*'], $table, ['uid' => $uid])->fetchAssociative();
        self::assertIsArray($row);

        return $row;
    }

    private function renderRow(bool $forThePageModule = false): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'card' => $this->row('tx_headlinertestcards_card', 1),
            'single' => $this->row('tx_headlinertestcards_single', 5),
            // a row of a table that no inline field of the element links: the table is not known
            'orphan' => ['uid' => 99, 'title' => 'Orphan'],
            // the content element, which the card points to (parent = 21)
            'data' => ['uid' => 21, 'CType' => 'text', 'header_tag' => 'h2'],
        ]);

        return trim($view->render('Row'));
    }

    #[Test]
    public function aRowGivesTheTextTheTagAndTheMarkOfARecord(): void
    {
        $html = $this->renderRow(true);

        // the label field "title" is the text, the field "tag" the tag
        self::assertStringContainsString('<h3 data-headliner-edit="tx_headlinertestcards_card:1:tag" data-headliner-element="21">First card</h3>', $html);
    }

    #[Test]
    public function theTableIsFoundFromTheInlineFieldOfTheContentElement(): void
    {
        $html = $this->renderRow(true);

        // no table given for the second heading: it is the one that element 21 links the card from
        self::assertMatchesRegularExpression('#<h3 class="from-data" data-headliner-edit="tx_headlinertestcards_card:1:tag"#', $html);
    }

    #[Test]
    public function theOnlyTagFieldOfATableIsFoundAndNeedsNoTagField(): void
    {
        self::assertStringContainsString('data-headliner-edit="tx_headlinertestcards_single:5:level"', $this->renderRow(true));
        self::assertStringContainsString('>Single</h4>', $this->renderRow(true));
    }

    #[Test]
    public function aTextFieldNamesTheFieldForTheText(): void
    {
        self::assertStringContainsString('<h3 class="named-text">First card</h3>', $this->renderRow());
    }

    #[Test]
    public function aRowWhoseTableIsNotKnownGivesNoHeading(): void
    {
        self::assertStringNotContainsString('Orphan', $this->renderRow());
    }

    #[Test]
    public function aVisitorGetsTheHeadingsWithoutMarks(): void
    {
        $html = $this->renderRow();

        self::assertStringNotContainsString('data-headliner', $html);
        self::assertStringContainsString('<h3>First card</h3>', $html);
    }
}
