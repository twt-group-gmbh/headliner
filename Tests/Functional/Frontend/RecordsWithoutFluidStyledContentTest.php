<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Records of a table of their own without fluid_styled_content, the way a Content Block without it has them: the base
 * set, a template of its own, the rows turned into records by the core's data processor, the real frontend rendering,
 * and the box behind it.
 */
final class RecordsWithoutFluidStyledContentTest extends AbstractFrontendTestCase
{
    protected array $coreExtensionsToLoad = ['rte_ckeditor', 'workspaces'];

    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    private const TABLE = 'tx_headlinertestcards_card';

    private const PAGE = <<<'TYPOSCRIPT'
        page = PAGE
        page.10 = FLUIDTEMPLATE
        page.10.template = TEXT
        page.10.template.value (
        <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers" data-namespace-typo3-fluid="true">
        <f:for each="{rows}" as="item"><headliner:headline record="{item.record}" tagField="tag">{item.record.title}</headliner:headline>{item.record -> f:render.text(field: 'text')}</f:for>
        <f:for each="{rows}" as="item"><headliner:text row="{item.data}" table="tx_headlinertestcards_card"><h5>Raw heading</h5></headliner:text></f:for>
        </html>
        )
        page.10.dataProcessing {
            10 = database-query
            10 {
                table = tx_headlinertestcards_card
                pidInList = 1
                selectFields = *
                includeRecordsWithoutDefaultTranslation = 1
                as = rows
                dataProcessing {
                    10 = record-transformation
                    10.table = tx_headlinertestcards_card
                }
            }
        }
        TYPOSCRIPT;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/CardsOnRoot.csv');
        $this->writeSite(['headliner' => ['editRteHeadings' => true]], self::PAGE, ['twtgroup/headliner']);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['EXEC_TIME'] = time();
    }

    private function inspected(): string
    {
        return $this->render((new InternalRequest('https://website.local/'))->withAttribute(InspectRequest::ATTRIBUTE, true));
    }

    #[Test]
    public function theRecordNamesItselfAndTheHeadingsOfItsRichText(): void
    {
        $html = $this->inspected();

        self::assertStringContainsString('<h3 data-headliner-edit="' . self::TABLE . ':1:tag">First card</h3>', $html);
        // the heading in the text knows its record and field without a wrapper
        self::assertStringContainsString('<h3 data-headliner-record="' . self::TABLE . ':1" data-headliner-field="text">Card text heading</h3>', $html);
    }

    #[Test]
    public function aRowAsAnArrayNamesItsRecordAndFieldWithTheTextViewHelper(): void
    {
        // the row as the data processor gives it, without the record: headliner:text names the record and the field
        self::assertStringContainsString('<h5 data-headliner-record="' . self::TABLE . ':1" data-headliner-field="text">Raw heading</h5>', $this->inspected());
    }

    #[Test]
    public function aVisitorGetsTheHeadingsWithoutAnyMark(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('data-headliner', $html);
        self::assertStringContainsString('<h3>First card</h3>', $html);
        self::assertStringContainsString('<h3>Card text heading</h3>', $html);
    }

    #[Test]
    public function theBoxChangesTheTitleOfACardAndTheHeadingInItsText(): void
    {
        $this->setUpBackendUser(1);

        $html = $this->structureBox()->render($this->backendRequest(), 1, [0], '');

        self::assertStringContainsString('data-headliner-edit="' . self::TABLE . ':1:tag"', $html);
        self::assertStringContainsString('data-headliner-rte="' . self::TABLE . ':1:text:0"', $html);
    }
}
