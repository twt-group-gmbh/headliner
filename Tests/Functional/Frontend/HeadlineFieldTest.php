<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Domain\RawRecord;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\ComputedProperties;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * The text of the heading from a field of the record (the label field of the table, or the one named with textField), as `f:render.text` gives it: the record is written once.
 */
final class HeadlineFieldTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Cards.csv');
    }

    private function renderField(bool $forThePageModule = false): string
    {
        $factory = $this->get(RecordFactory::class);
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $element = $connection->select(['*'], 'tt_content', ['uid' => 4])->fetchAssociative();
        $card = $this->getConnectionPool()->getConnectionForTable('tx_headlinertestcards_card')
            ->select(['*'], 'tx_headlinertestcards_card', ['uid' => 1])->fetchAssociative();
        self::assertIsArray($element);
        self::assertIsArray($card);

        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'record' => $factory->createResolvedRecordFromDatabaseRow('tt_content', $element),
            'card' => $factory->createResolvedRecordFromDatabaseRow('tx_headlinertestcards_card', $card),
            // a record whose label field is a select
            'levelled' => new Record(new RawRecord(9, 1, ['level' => 'h3'], new ComputedProperties(), 'tx_headlinertestcards_levellabel'), ['level' => 'h3']),
        ]);

        return trim($view->render('Field'));
    }

    #[Test]
    public function theTextComesFromTheHeaderAndTheSubheaderFieldWithoutAnyContent(): void
    {
        $html = $this->renderField();

        self::assertStringContainsString('>With subheader</h2>', $html);
        self::assertStringContainsString('>Own subheader</h5>', $html);
    }

    #[Test]
    public function aTextFieldNamesTheTextOfARecordOfAnotherTable(): void
    {
        self::assertStringContainsString('<h3>First card</h3>', $this->renderField());
    }

    #[Test]
    public function withoutATextFieldTheLabelFieldOfTheTableIsTheText(): void
    {
        // the card has the label field "title": two headings with its text, one by name and one by the label
        self::assertSame(2, substr_count($this->renderField(), '<h3>First card</h3>'));
    }

    #[Test]
    public function contentBetweenTheTagsWinsOverTheField(): void
    {
        self::assertStringContainsString('>Own content</h2>', $this->renderField());
    }

    #[Test]
    public function aTextFieldThatIsNotThereGivesNoHeading(): void
    {
        $html = $this->renderField();

        // the text field "missing": nothing is output for it, and no empty heading
        self::assertStringNotContainsString('<h3></h3>', $html);
        self::assertSame(2, substr_count($html, '<h3'));
    }

    #[Test]
    public function aLabelFieldThatIsNoTextGivesNoHeading(): void
    {
        // the label field of that table is a select: no text, so no heading (the level h3 must not become the text)
        self::assertStringNotContainsString('>h3<', $this->renderField());
    }

    #[Test]
    public function theMarksForThePageModuleAreTheSameWithAField(): void
    {
        $html = $this->renderField(true);

        self::assertStringContainsString('<h3 data-headliner-edit="tx_headlinertestcards_card:1:tag">First card</h3>', $html);
    }
}
