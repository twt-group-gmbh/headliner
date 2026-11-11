<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * The text of the heading from a field of the record, as `f:render.text` gives it: the record is written once.
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
    public function aFieldNamesTheTextOfARecordOfAnotherTable(): void
    {
        self::assertStringContainsString('<h3>First card</h3>', $this->renderField());
    }

    #[Test]
    public function contentBetweenTheTagsWinsOverTheField(): void
    {
        self::assertStringContainsString('>Own content</h2>', $this->renderField());
    }

    #[Test]
    public function aFieldThatIsNotThereGivesNoHeading(): void
    {
        $html = $this->renderField();

        // a field and a standard field that the card does not have: nothing is output for them, only the card with a title
        self::assertSame(1, substr_count($html, '<h3'));
        self::assertStringNotContainsString('<h3></h3>', $html);
    }

    #[Test]
    public function theMarksForThePageModuleAreTheSameWithAField(): void
    {
        $html = $this->renderField(true);

        self::assertStringContainsString('<h3 data-headliner-edit="tx_headlinertestcards_card:1:tag">First card</h3>', $html);
    }
}
