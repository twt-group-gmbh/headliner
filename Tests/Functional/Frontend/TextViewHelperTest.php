<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The rich text of a row that came as an array: the ViewHelper names the record and the field in its headings, for the
 * page module only.
 */
final class TextViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $data
     */
    private function render(bool $forThePageModule, array $row, string $table, ?array $data = null, string $template = 'Text'): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple(array_filter(['row' => $row, 'table' => $table, 'data' => $data], static fn(mixed $value): bool => $value !== null));

        return trim($view->render($template));
    }

    #[Test]
    public function everyHeadingOfTheTextNamesTheRecordAndTheFieldForThePageModule(): void
    {
        $html = $this->render(true, ['uid' => 7], 'tx_headlinertestcards_card');

        self::assertStringContainsString('<h3 data-headliner-record="tx_headlinertestcards_card:7" data-headliner-field="text">In the text</h3>', $html);
        // the attributes it had stay
        self::assertStringContainsString('<h4 data-headliner-record="tx_headlinertestcards_card:7" data-headliner-field="text" class="a">Next</h4>', $html);
    }

    #[Test]
    public function aTranslationIsNamedByItsOwnUid(): void
    {
        $html = $this->render(true, ['uid' => 7, '_LOCALIZED_UID' => 70], 'tx_headlinertestcards_card');

        self::assertStringContainsString('data-headliner-record="tx_headlinertestcards_card:70"', $html);
    }

    #[Test]
    public function theTableIsFoundFromTheInlineFieldOfTheElementIfNoneIsGiven(): void
    {
        $html = $this->render(true, ['uid' => 7, 'parent' => 21], '', ['uid' => 21, 'CType' => 'text']);

        self::assertStringContainsString('data-headliner-record="tx_headlinertestcards_card:7"', $html);
    }

    #[Test]
    public function aVisitorGetsTheTextAsItIs(): void
    {
        self::assertSame(
            '<h3>In the text</h3><p>x</p><h4 class="a">Next</h4>',
            $this->render(false, ['uid' => 7], 'tx_headlinertestcards_card')
        );
    }

    #[Test]
    public function withoutATableOrAUidTheTextIsLeftAlone(): void
    {
        self::assertStringNotContainsString('data-headliner', $this->render(true, ['uid' => 7], ''));
        self::assertStringNotContainsString('data-headliner', $this->render(true, ['title' => 'No uid'], 'tx_headlinertestcards_card'));
    }

    #[Test]
    public function theFieldIsTheOneRichTextFieldOfTheTableIfNoneIsGiven(): void
    {
        $html = $this->render(true, ['uid' => 7], 'tx_headlinertestcards_card', template: 'TextAuto');

        self::assertStringContainsString('<h3 data-headliner-record="tx_headlinertestcards_card:7" data-headliner-field="text">In the text</h3>', $html);
    }

    #[Test]
    public function aTableWithoutARichTextFieldGivesNoField(): void
    {
        // the table has a title and a select only: nothing to name
        self::assertStringNotContainsString('data-headliner', $this->render(true, ['uid' => 7], 'tx_headlinertestcards_single', template: 'TextAuto'));
    }
}
