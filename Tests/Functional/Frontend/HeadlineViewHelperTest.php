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
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The ViewHelper in a template of its own, as an integrator uses it.
 */
final class HeadlineViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

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
        $view->assignMultiple(['record' => $record, 'card' => $card, 'notARecord' => ['uid' => 5, 'header_tag' => 'h5']]);

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
    public function somethingThatIsNoRecordIsIgnored(): void
    {
        $html = $this->renderWithRecords(true, self::element(), self::row('cards', 7, []));

        // an array does not give a tag or a mark: the default
        self::assertStringContainsString('<h2>Array</h2>', $html);
    }
}
