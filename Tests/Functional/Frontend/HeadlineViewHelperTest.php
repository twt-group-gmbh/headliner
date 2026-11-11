<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3\CMS\Core\Domain\RawRecord;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\ComputedProperties;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The ViewHelper in a template of its own, as an integrator uses it.
 */
final class HeadlineViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    /**
     * @return array<string, mixed>
     */
    private static function content(): array
    {
        return ['uid' => 12, 'CType' => 'text', 'header_tag' => 'h2', 'header_layout' => 2, 'subheader_tag' => 'h3'];
    }

    private function renderHeaderPartial(mixed $record): string
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            partialRootPaths: [__DIR__ . '/../../../Resources/Private/Partials'],
            request: (new ServerRequest('https://website.local/', 'GET'))->withAttribute(InspectRequest::ATTRIBUTE, true),
        ));
        $view->assign('record', $record);

        return trim($view->render('HeaderPartial'));
    }

    #[Test]
    public function theHeaderPartialTakesTagLayoutAndMarkFromTheRecordItIsGiven(): void
    {
        // the partial is given the tag h3 as a value, and a record that says h2: the record is what it uses
        self::assertStringContainsString(
            '<h2 class="ce-headline-layout-2" data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Hi</h2>',
            $this->renderHeaderPartial(self::element())
        );
    }

    #[Test]
    public function theHeaderPartialStillRendersTheHeadingWithoutARecord(): void
    {
        // no `{record}` in the template (a content element without lib.contentElement): the heading, but no mark
        self::assertSame('<h3>Hi</h3>', $this->renderHeaderPartial(null));
    }

    /**
     * @param array<string, mixed> $properties
     */
    private static function row(string $type, int $uid, array $properties, ?int $localizedUid = null): Record
    {
        return new Record(new RawRecord($uid, 1, $properties, new ComputedProperties(localizedUid: $localizedUid), $type), $properties);
    }

    /**
     * The template "Record" with a content element (`record`), a card (`card`) and something that is no record.
     */
    private function renderWithRecords(bool $forThePageModule, Record $record, Record $card): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple(['record' => $record, 'card' => $card]);

        return trim($view->render('Record'));
    }

    private static function element(?int $localizedUid = null): Record
    {
        return self::row('tt_content.text', 12, self::content(), $localizedUid);
    }

    #[Test]
    public function aRecordGivesTheTagTheLayoutClassAndTheMark(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, ['tag' => 'h4']));

        // no `data`, no `tag`, no `layout`: all of it from the record
        self::assertStringContainsString('<h2 class="ce-headline-layout-2" data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Header</h2>', $html);
    }

    #[Test]
    public function theSubheaderOfARecordTakesItsOwnTagField(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, ['tag' => 'h4']));

        self::assertStringContainsString('<h3 class="ce-headline-layout-2" data-headliner-edit="tt_content:12:subheader_tag" data-headliner-element="12">Subheader</h3>', $html);
    }

    #[Test]
    public function theSubheaderOfARecordWithoutATagIsOneLevelBelowItsHeader(): void
    {
        $element = self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => 'h2', 'subheader_tag' => '']);

        self::assertStringContainsString('>Subheader</h3>', $this->renderWithRecords(false, $element, self::row('cards', 7, [])));
    }

    #[Test]
    public function aRecordOfAnotherTableNamesItsOwnTagField(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, ['tag' => 'h4']));

        // the card is not the content element: the mark is the card's, the layout class is left out (it has no layout).
        // This template has no `data`, so the element of the card is not known: with one it is named (see above).
        self::assertStringContainsString('<h4 data-headliner-edit="cards:7:tag">Card</h4>', $html);
    }

    #[Test]
    public function aTranslationIsNamedByItsOwnUidInARecord(): void
    {
        $html = $this->renderWithRecords(true, self::element(40), self::row('cards', 7, ['tag' => 'h4'], 70));

        self::assertStringContainsString('data-headliner-edit="tt_content:40:header_tag"', $html);
        self::assertStringContainsString('data-headliner-edit="cards:70:tag"', $html);
    }

    #[Test]
    public function aTagThatIsGivenWinsOverTheRecord(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, ['tag' => 'h4']));

        // tag="h6" with subheader: the tag of the subheader is the one that was given
        self::assertStringContainsString('>Explicit</h6>', $html);
    }

    #[Test]
    public function aRecordWithoutTheTagFieldHasNoMarkAndTheDefaultTag(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, ['title' => 'No tag here']));

        // never the header of the content element for a card
        self::assertStringContainsString('<h2>Card</h2>', $html);
        self::assertStringNotContainsString('cards:7', $html);
    }

    #[Test]
    public function anEmptyTagOfARecordKeepsTheLayoutOfOlderContent(): void
    {
        $element = self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => '', 'header_layout' => 4]);

        self::assertStringContainsString('<h4 class="ce-headline-layout-4">Header</h4>', $this->renderWithRecords(false, $element, self::row('cards', 7, [])));
    }

    #[Test]
    public function aVisitorGetsTheTagButNeverTheMarks(): void
    {
        $html = $this->renderWithRecords(false, self::element(), self::row('cards', 7, ['tag' => 'h4']));

        self::assertStringNotContainsString('data-headliner', $html);
        self::assertStringContainsString('<h2 class="ce-headline-layout-2">Header</h2>', $html);
        self::assertStringContainsString('<h4>Card</h4>', $html);
    }

    #[Test]
    public function anArrayIsNoRecordAndIsRefused(): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        // `record` takes record objects; an array is a row or values, and goes to headliner:headline.values
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: new ServerRequest('https://website.local/', 'GET'),
        ));
        $view->assignMultiple(['record' => ['uid' => 7], 'card' => self::row('cards', 7, [])]);
        $view->render('Record');
    }

    /**
     * The template "Parent": a card with the given tag field, below the header of `parent`.
     *
     * @param array<string, mixed> $card
     */
    private function renderBelow(mixed $parent, array $card, bool $forThePageModule = false): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple(['parent' => $parent, 'card' => self::row('cards', 7, $card), 'element' => self::element()]);

        return trim($view->render('Parent'));
    }

    #[Test]
    public function aCardWithoutATagIsOneLevelBelowTheHeaderOfTheParent(): void
    {
        $html = $this->renderBelow(self::element(), ['tag' => '']);

        // the header of the element is an h2
        self::assertStringContainsString('<h3>Default</h3>', $html);
    }

    #[Test]
    public function aCardWithoutTheTagFieldIsOneLevelBelowToo(): void
    {
        // a forgotten or misspelled tagField no longer ends in an h2 below an h2
        self::assertStringContainsString('<h3>Default</h3>', $this->renderBelow(self::element(), ['title' => 'No tag field']));
    }

    #[Test]
    public function theOffsetSaysHowManyLevelsBelow(): void
    {
        $html = $this->renderBelow(self::element(), ['tag' => '']);

        self::assertStringContainsString('<h4>Two</h4>', $html);
        self::assertStringContainsString('<h1>Above</h1>', $html);
    }

    #[Test]
    public function theTagTheEditorChoseWinsOverTheParent(): void
    {
        $html = $this->renderBelow(self::element(), ['tag' => 'h4']);

        self::assertStringContainsString('<h4>Default</h4>', $html);
        self::assertStringContainsString('<h4>Two</h4>', $html);
        self::assertStringContainsString('<h5>Explicit</h5>', $html);
    }

    #[Test]
    public function theParentCanBeTheArrayOfAContentElement(): void
    {
        self::assertStringContainsString('<h3>Default</h3>', $this->renderBelow(self::content(), ['tag' => '']));
    }

    #[Test]
    public function theParentWithoutATagTakesItsLayout(): void
    {
        $parent = self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => '', 'header_layout' => 3]);

        self::assertStringContainsString('<h4>Default</h4>', $this->renderBelow($parent, ['tag' => '']));
    }

    #[Test]
    public function belowTheLastLevelItIsADiv(): void
    {
        $parent = self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => 'h6']);

        self::assertStringContainsString('<div>Default</div>', $this->renderBelow($parent, ['tag' => '']));
    }

    #[Test]
    public function belowADivItIsADiv(): void
    {
        $parent = self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => 'div']);

        self::assertStringContainsString('<div>Default</div>', $this->renderBelow($parent, ['tag' => '']));
    }

    #[Test]
    public function aParentThatIsNeitherARecordNorAnArrayIsRefused(): void
    {
        $this->expectException(InvalidArgumentValueException::class);

        $this->renderBelow('h2', ['tag' => '']);
    }

    /**
     * The template "Auto": a record with one tag field, a record with two, and the content element, with no `tagField`,
     * `parent` or `default` anywhere.
     *
     * @param array<string, mixed> $single
     * @param array<string, mixed> $element
     * @param array<string, mixed>|null $data
     * @param array<string, mixed> $settings
     */
    private function renderAuto(array $single, array $element = ['CType' => 'text', 'header_tag' => ''], ?array $data = null, array $settings = [], bool $forThePageModule = true): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple(array_filter([
            'single' => self::row('tx_headlinertestcards_single', 5, $single),
            'card' => self::row('tx_headlinertestcards_card', 6, ['tag' => 'h3', 'subtag' => 'h4']),
            'element' => self::row('tt_content.text', 12, $element),
            'data' => $data,
            'settings' => $settings,
        ], static fn(mixed $value): bool => $value !== null));

        return trim($view->render('Auto'));
    }

    #[Test]
    public function theOnlyTagFieldOfARecordIsFoundWithoutTagField(): void
    {
        $html = $this->renderAuto(['level' => 'h4']);

        self::assertStringContainsString('<h4 data-headliner-edit="tx_headlinertestcards_single:5:level">Single</h4>', $html);
    }

    #[Test]
    public function aRecordWithTwoTagFieldsNeedsTagFieldAndGetsNoMarkWithout(): void
    {
        $html = $this->renderAuto(['level' => 'h4']);

        // which of the two is meant is not known: the default tag, no mark
        self::assertStringContainsString('<h2>Two</h2>', $html);
        self::assertStringNotContainsString('tx_headlinertestcards_card:6', $html);
    }

    #[Test]
    public function aRecordOfAnotherTableIsBelowTheHeaderOfTheElementInDataWithoutParent(): void
    {
        $html = $this->renderAuto(['level' => ''], data: ['uid' => 12, 'CType' => 'text', 'header_tag' => 'h4']);

        self::assertStringContainsString('>Single</h5>', $html);
    }

    #[Test]
    public function withoutDataARecordWithoutATagGetsTheDefaultTag(): void
    {
        self::assertStringContainsString('>Single</h2>', $this->renderAuto(['level' => '']));
    }

    #[Test]
    public function theDefaultTagIsTheSettingOfTheContentElementWithoutDefault(): void
    {
        $html = $this->renderAuto(['level' => ''], settings: ['defaultHeaderType' => 4]);

        self::assertStringContainsString('>Single</h4>', $html);
        // the content element has no tag and no layout either
        self::assertStringContainsString('>Header</h4>', $html);
    }

    #[Test]
    public function aVisitorGetsTheTagsOfTheAutomaticFieldWithoutMarks(): void
    {
        $html = $this->renderAuto(['level' => 'h3'], forThePageModule: false);

        self::assertStringContainsString('<h3>Single</h3>', $html);
        self::assertStringNotContainsString('data-headliner', $html);
    }

    #[Test]
    public function aSubheaderWithATagOfItsOwnKeepsItWhateverTheParentSays(): void
    {
        // the record has the subheader tag h3: it wins over the three levels below the parent
        self::assertStringContainsString('>Subheader</h3>', $this->renderBelow(self::element(), ['tag' => '']));
    }

    #[Test]
    public function theMarkOfTheCardStaysTheCards(): void
    {
        $html = $this->renderBelow(self::element(), ['tag' => ''], true);

        self::assertStringContainsString('<h3 data-headliner-edit="cards:7:tag">Default</h3>', $html);
    }
}
