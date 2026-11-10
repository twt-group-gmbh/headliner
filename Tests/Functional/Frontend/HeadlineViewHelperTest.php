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
     * @param array<string, mixed>|Record $data
     */
    private function render(bool $forThePageModule, array|Record $data): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assign('data', $data);

        return $view->render('Own');
    }

    /**
     * @return array<string, mixed>
     */
    private static function content(): array
    {
        return ['uid' => 12, 'CType' => 'text', 'header_tag' => 'h2', 'header_layout' => 2, 'subheader_tag' => 'h3'];
    }

    #[Test]
    public function theRecordIsTakenFromTheDataOfTheContentElementForThePageModule(): void
    {
        $html = $this->render(true, self::content());

        self::assertStringContainsString('<h2 class="ce-headline-layout-2" data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Header</h2>', $html);
        self::assertStringContainsString('data-headliner-edit="tt_content:12:subheader_tag" data-headliner-element="12">Subheader</h3>', $html);
    }

    #[Test]
    public function aTranslationIsNamedByItsOwnUid(): void
    {
        $html = $this->render(true, ['_LOCALIZED_UID' => 40] + self::content());

        self::assertStringContainsString('data-headliner-edit="tt_content:40:header_tag"', $html);
    }

    #[Test]
    public function anExplicitEditArgumentWins(): void
    {
        $html = $this->render(true, self::content());

        self::assertStringContainsString('<h4 data-headliner-edit="tt_content:99:header_tag" data-headliner-element="12">Explicit</h4>', $html);
    }

    #[Test]
    public function aVisitorNeverGetsTheMark(): void
    {
        $html = $this->render(false, self::content());

        self::assertStringNotContainsString('data-headliner-edit', $html);
        self::assertStringContainsString('<h2 class="ce-headline-layout-2">Header</h2>', $html);
    }

    #[Test]
    public function withoutDataThereIsNoMark(): void
    {
        $html = $this->render(true, []);

        self::assertStringNotContainsString('tt_content:0', $html);
        // only the explicit one
        self::assertSame(1, substr_count($html, 'data-headliner-edit'));
    }

    #[Test]
    public function aRecordObjectAsADataIsNamedToo(): void
    {
        // a Content Block gets the content element as a record object, not as an array
        $properties = self::content();
        $record = new Record(new RawRecord(12, 1, $properties, new ComputedProperties(), 'tt_content.text'), $properties);

        $html = $this->render(true, $record);

        self::assertStringContainsString('data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Header</h2>', $html);
        self::assertStringContainsString('data-headliner-edit="tt_content:12:subheader_tag" data-headliner-element="12">Subheader</h3>', $html);
    }

    #[Test]
    public function everyHeadingNamesItsContentElementForThePageModule(): void
    {
        $html = $this->render(true, self::content());

        // also the one with an explicit edit: it can be the record of a collection, the element is still the one of data
        self::assertSame(3, substr_count($html, 'data-headliner-element="12"'));
    }

    #[Test]
    public function aVisitorGetsNoElementMarkEither(): void
    {
        self::assertStringNotContainsString('data-headliner-element', $this->render(false, self::content()));
    }

    #[Test]
    public function withoutDataThereIsNoElementMark(): void
    {
        self::assertStringNotContainsString('data-headliner-element', $this->render(true, []));
    }

    private static function card(int $uid): Record
    {
        return new Record(new RawRecord($uid, 1, ['title' => 'A card'], new ComputedProperties(), 'cards'), ['title' => 'A card']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderCard(bool $forThePageModule, array $data, mixed $card): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple(['data' => $data, 'card' => $card]);

        return trim($view->render('Card'));
    }

    #[Test]
    public function aRecordIsMarkedWithItselfAndItsElementForThePageModule(): void
    {
        $html = $this->renderCard(true, self::content(), self::card(7));

        self::assertStringContainsString('data-headliner-record="cards:7"', $html);
        self::assertStringContainsString('data-headliner-element="12"', $html);
        // what is inside comes out as it is
        self::assertStringContainsString('<h3>Title</h3><p>Hello</p>', $html);
    }

    #[Test]
    public function aHeadlineInARecordDoesNotTakeTheTagFieldOfTheElement(): void
    {
        $html = $this->renderCard(true, self::content(), self::card(7));

        // outside of the record the heading is the header of the element, as before
        self::assertStringContainsString('<h2 data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Outside</h2>', $html);
        // inside it is not: the page module finds the field from the record. Only an explicit edit names a field.
        self::assertStringContainsString('<h3 data-headliner-element="12">Inside</h3>', $html);
        self::assertStringContainsString('<h4 data-headliner-edit="cards:7:tag" data-headliner-element="12">Explicit</h4>', $html);
    }

    #[Test]
    public function aVisitorGetsTheCardAndNothingElse(): void
    {
        self::assertStringNotContainsString('data-headliner', $this->renderCard(false, self::content(), self::card(7)));
        self::assertStringContainsString('<h3>Title</h3><p>Hello</p>', $this->renderCard(false, self::content(), self::card(7)));
    }

    #[Test]
    public function withoutARecordThereIsNoMark(): void
    {
        self::assertStringNotContainsString('data-headliner-record', $this->renderCard(true, self::content(), null));
        self::assertStringContainsString('<h3>Title</h3><p>Hello</p>', $this->renderCard(true, self::content(), null));
    }

    /**
     * @param array<string, mixed> $card
     * @param array<string, mixed> $data
     */
    private function renderArrayCard(bool $forThePageModule, string $table, array $card, array $data = []): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple(['card' => $card, 'table' => $table, 'data' => $data]);

        return trim($view->render('ArrayCard'));
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
    public function theHeaderPartialTakesTheMarkFromTheRecordItIsGiven(): void
    {
        self::assertStringContainsString(
            'data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Hi</h3>',
            $this->renderHeaderPartial(self::element())
        );
    }

    #[Test]
    public function theHeaderPartialStillRendersTheHeadingWithoutARecord(): void
    {
        // no `{record}` in the template (a content element without lib.contentElement): the heading, but no mark
        self::assertSame('<h3>Hi</h3>', $this->renderHeaderPartial(null));
    }

    #[Test]
    public function aRowAsAnArrayIsMarkedWithTheTableThatIsGiven(): void
    {
        $html = $this->renderArrayCard(true, 'cards', ['uid' => 7, 'title' => 'A card']);

        self::assertStringContainsString('data-headliner-record="cards:7"', $html);
        self::assertStringContainsString('<h3>Title</h3><p>Hello</p>', $html);
    }

    #[Test]
    public function theTableOfARowIsFoundFromTheFieldOfTheElementThatLinksIt(): void
    {
        // no `table`: the element has an inline field for that table, and the row points to the element with its foreign_field
        $html = $this->renderArrayCard(true, '', ['uid' => 7, 'parent' => 21], ['uid' => 21, 'CType' => 'text']);

        self::assertStringContainsString('data-headliner-record="tx_headlinertestcards_card:7"', $html);
    }

    #[Test]
    public function theTableOfARowThatPointsToAnotherElementIsNotGuessed(): void
    {
        $html = $this->renderArrayCard(true, '', ['uid' => 7, 'parent' => 99], ['uid' => 21, 'CType' => 'text']);

        self::assertStringNotContainsString('data-headliner-record', $html);
    }

    #[Test]
    public function aRowAsAnArrayWithoutATableHasNoMark(): void
    {
        self::assertStringNotContainsString('data-headliner-record', $this->renderArrayCard(true, '', ['uid' => 7]));
    }

    #[Test]
    public function aVisitorGetsTheRowAsAnArrayWithoutMarks(): void
    {
        self::assertStringNotContainsString('data-headliner', $this->renderArrayCard(false, 'cards', ['uid' => 7]));
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
    public function argumentsGivenExplicitlyWinOverTheRecord(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, ['tag' => 'h4']));

        // tag and subheaderTag given: h5 as the header, so the subheader is the one that was given
        self::assertStringContainsString('>Explicit</h6>', $html);
        self::assertStringContainsString('<h2 class="ce-headline-layout-2" data-headliner-edit="tt_content:99:header_tag" data-headliner-element="12">Edit</h2>', $html);
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

        // `record` takes record objects: an array is a row, or the values, and goes elsewhere
        $this->renderCard(true, self::content(), ['uid' => 7]);
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
     * The template "Subheader": a subheader below `parent`, one with a tag of its own, one with an offset below its record.
     *
     * @param array<string, mixed> $parent
     */
    private function renderSubheader(array $parent, Record $element): string
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: new ServerRequest('https://website.local/', 'GET'),
        ));
        $view->assignMultiple(['parent' => $parent, 'element' => $element]);

        return trim($view->render('Subheader'));
    }

    #[Test]
    public function aSubheaderWithoutATagIsOneLevelBelowTheHeaderOfItsParent(): void
    {
        $html = $this->renderSubheader(['header_tag' => 'h4'], self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => 'h2', 'subheader_tag' => '']));

        self::assertStringContainsString('<h5>Below</h5>', $html);
    }

    #[Test]
    public function theTagOfASubheaderIsItsOwnTagAndNotThatOfTheHeader(): void
    {
        $html = $this->renderSubheader(['header_tag' => 'h2'], self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => 'h2', 'subheader_tag' => '']));

        self::assertStringContainsString('<h6>Own</h6>', $html);
    }

    #[Test]
    public function aSubheaderIsOffsetLevelsBelowItsHeader(): void
    {
        $html = $this->renderSubheader(['header_tag' => 'h2'], self::row('tt_content.text', 12, ['CType' => 'text', 'header_tag' => 'h2', 'subheader_tag' => '']));

        self::assertStringContainsString('<h4>Offset</h4>', $html);
    }

    #[Test]
    public function aSubheaderIgnoresTheParent(): void
    {
        // the subheader is one level below its own header (h2): h3, not three below
        self::assertStringContainsString('>Subheader</h3>', $this->renderBelow(self::element(), ['tag' => '']));
    }

    #[Test]
    public function theMarkOfTheCardStaysTheCards(): void
    {
        $html = $this->renderBelow(self::element(), ['tag' => ''], true);

        self::assertStringContainsString('<h3 data-headliner-edit="cards:7:tag">Default</h3>', $html);
    }
}
