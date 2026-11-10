<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\DataProcessing\RecordTransformationProcessor;

/**
 * The headings in the rich text of a record get their record and field from the record itself, not from a wrapper in
 * the template: the marks are put into the field value when the record is created for the page module's own request.
 */
final class RichTextHeadingMarkTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    private const TABLE = 'tx_headlinertestcards_card';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Cards.csv');
    }

    private function text(int $uid, bool $forThePageModule, ?int $localizedUid = null): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $GLOBALS['TYPO3_REQUEST'] = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $row = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE)
            ->select(['*'], self::TABLE, ['uid' => $uid])->fetchAssociative();
        self::assertIsArray($row);
        if ($localizedUid !== null) {
            // as the frontend gives a translation: the original, with the uid of the translation beside it
            $row['_LOCALIZED_UID'] = $localizedUid;
        }

        return (string) $this->get(RecordFactory::class)->createResolvedRecordFromDatabaseRow(self::TABLE, $row)->get('text');
    }

    #[Test]
    public function theHeadingsOfARichTextNameTheirRecordAndFieldForThePageModule(): void
    {
        self::assertSame(
            '<p>x</p><h3 data-headliner-record="' . self::TABLE . ':1" data-headliner-field="text">Card text heading</h3>',
            $this->text(1, true)
        );
    }

    #[Test]
    public function aTranslationIsNamedByItsOwnUid(): void
    {
        self::assertStringContainsString('data-headliner-record="' . self::TABLE . ':70"', $this->text(1, true, 70));
    }

    #[Test]
    public function aVisitorGetsTheTextAsItIs(): void
    {
        self::assertSame('<p>x</p><h3>Card text heading</h3>', $this->text(1, false));
    }

    #[Test]
    public function theMarksSurviveTheRichTextRenderingOfTheFrontend(): void
    {
        $marked = $this->text(1, true);
        $this->writeSite([], "page = PAGE\npage.10 = TEXT\npage.10.value = " . str_replace("\n", '', $marked) . "\npage.10.parseFunc =< lib.parseFunc_RTE\n");

        self::assertStringContainsString(
            '<h3 data-headliner-record="' . self::TABLE . ':1" data-headliner-field="text">Card text heading</h3>',
            $this->render()
        );
    }

    #[Test]
    public function rowsOfADataProcessorAreMarkedAfterTheCoreTurnedThemIntoRecords(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://website.local/', 'GET'))->withAttribute(InspectRequest::ATTRIBUTE, true);
        $row = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE)
            ->select(['*'], self::TABLE, ['uid' => 1])->fetchAssociative();
        self::assertIsArray($row);

        // what an integrator adds below `database-query`: `10 = record-transformation`
        $processed = (new RecordTransformationProcessor($this->get(RecordFactory::class)))->process(
            GeneralUtility::makeInstance(ContentObjectRenderer::class),
            [],
            ['table' => self::TABLE, 'as' => 'cards'],
            ['data' => [$row]]
        );

        self::assertStringContainsString('data-headliner-record="' . self::TABLE . ':1"', (string) $processed['cards'][0]->get('text'));
    }
}
