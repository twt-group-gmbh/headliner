<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\HeadingExtractor;

final class HeadingExtractorTest extends TestCase
{
    #[Test]
    public function extractsHeadingsInDocumentOrder(): void
    {
        $html = '<html><body><h1>Title</h1><p>Text</p><h2>Sub</h2><h3>Deep</h3></body></html>';

        self::assertEquals(
            [new Heading(1, 'Title'), new Heading(2, 'Sub'), new Heading(3, 'Deep')],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function stripsInnerMarkupAndNormalizesWhitespace(): void
    {
        $html = "<body><h2>  Hello <strong>big</strong>\n   world </h2></body>";

        self::assertEquals([new Heading(2, 'Hello big world')], (new HeadingExtractor())->extract($html));
    }

    #[Test]
    public function keepsEmptyHeadings(): void
    {
        self::assertEquals([new Heading(2, '')], (new HeadingExtractor())->extract('<body><h2> </h2></body>'));
    }

    #[Test]
    public function handlesUmlautsAndInvalidMarkup(): void
    {
        $html = '<body><h1>Größe & <em>Qualität</h1><div><h2>Über uns</body>';

        self::assertEquals(
            [new Heading(1, 'Größe & Qualität'), new Heading(2, 'Über uns')],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function returnsNothingForHtmlWithoutHeadings(): void
    {
        self::assertSame([], (new HeadingExtractor())->extract('<body><p>Hi</p></body>'));
        self::assertSame([], (new HeadingExtractor())->extract(''));
    }

    #[Test]
    public function readsWhereAHeadingCanBeEdited(): void
    {
        $html = '<body><h2 class="x" data-headliner-edit="tt_content:12:header_tag">Title</h2>'
            . '<h3 data-headliner-edit="tt_content:12:subheader_tag">Sub</h3><h4>RTE</h4></body>';

        self::assertEquals(
            [
                new Heading(2, 'Title', 'tt_content:12:header_tag'),
                new Heading(3, 'Sub', 'tt_content:12:subheader_tag'),
                new Heading(4, 'RTE'),
            ],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function listsAnEditableHeaderThatIsNoHeadingAsLevelZero(): void
    {
        $html = '<body><h1>Title</h1><div class="y" data-headliner-edit="tt_content:7:header_tag">Looks like one</div>'
            . '<div>Plain div</div></body>';

        self::assertEquals(
            [new Heading(1, 'Title'), new Heading(0, 'Looks like one', 'tt_content:7:header_tag')],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function knowsTheContentElementAHeadingIsIn(): void
    {
        $html = '<body><h1>Menu</h1><div id="c12" class="frame"><h2 data-headliner-edit="tt_content:12:header_tag">Title</h2>'
            . '<h3>From the text</h3></div></body>';

        self::assertEquals(
            [
                new Heading(1, 'Menu'),
                new Heading(2, 'Title', 'tt_content:12:header_tag', 12),
                new Heading(3, 'From the text', null, 12),
            ],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function takesTheNearestContentElementWhenTheyAreNested(): void
    {
        $html = '<body><div id="c1"><div id="c2"><h3>Inner</h3></div><h2>Outer</h2></div></body>';

        self::assertEquals(
            [new Heading(3, 'Inner', null, 2), new Heading(2, 'Outer', null, 1)],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function looksPastWrappersThatAreNoContentElement(): void
    {
        $html = '<body><div id="c5"><section id="main"><div id="c"><div id="c12abc"><h2>Text</h2></div></div></section></div></body>';

        self::assertEquals([new Heading(2, 'Text', null, 5)], (new HeadingExtractor())->extract($html));
    }

    private const PAGE = '<body><header><h1>Site name</h1></header><nav><h2>Menu</h2></nav>'
        . '<main id="content" class="page"><h1>Title</h1><section><div><h2>Deep</h2></div></section></main>'
        . '<footer><h2>Footer</h2></footer></body>';

    #[Test]
    public function marksHeadingsOutsideOfTheContentElement(): void
    {
        self::assertEquals(
            [
                new Heading(1, 'Site name', outside: true),
                new Heading(2, 'Menu', outside: true),
                new Heading(1, 'Title'),
                new Heading(2, 'Deep'),
                new Heading(2, 'Footer', outside: true),
            ],
            (new HeadingExtractor())->extract(self::PAGE, 'main')
        );
    }

    #[Test]
    public function readsTheWholePageWithoutASelector(): void
    {
        foreach ([null, '', '   '] as $selector) {
            $headings = (new HeadingExtractor())->extract(self::PAGE, $selector);

            self::assertCount(5, $headings);
            self::assertSame([false], array_values(array_unique(array_map(static fn(Heading $heading): bool => $heading->outside, $headings))));
        }
    }

    #[Test]
    public function readsTheWholePageIfTheSelectorMatchesNothing(): void
    {
        $headings = (new HeadingExtractor())->extract('<body><h1>One</h1><div><h2>Two</h2></div></body>', 'main');

        self::assertEquals([new Heading(1, 'One'), new Heading(2, 'Two')], $headings);
    }

    #[Test]
    public function understandsIdsClassesAndCombinations(): void
    {
        foreach (['#content', '.page', 'main.page', 'main#content', 'body > main', 'section, main'] as $selector) {
            $outside = array_map(
                static fn(Heading $heading): string => $heading->text,
                array_values(array_filter((new HeadingExtractor())->extract(self::PAGE, $selector), static fn(Heading $heading): bool => $heading->outside))
            );

            self::assertSame(['Site name', 'Menu', 'Footer'], $outside, $selector);
        }
    }

    #[Test]
    public function everyElementThatMatchesIsContent(): void
    {
        $html = '<body><h1>Outside</h1><article><h2>First</h2></article><article><h2>Second</h2></article></body>';

        self::assertEquals(
            [new Heading(1, 'Outside', outside: true), new Heading(2, 'First'), new Heading(2, 'Second')],
            (new HeadingExtractor())->extract($html, 'article')
        );
    }

    #[Test]
    public function readsTheWholePageIfTheSelectorIsInvalid(): void
    {
        $headings = (new HeadingExtractor())->extract(self::PAGE, 'main[[[');

        self::assertCount(5, $headings);
        self::assertFalse($headings[0]->outside);
    }

    #[Test]
    public function stillKnowsTheRecordBehindAHeadingInTheContent(): void
    {
        $html = '<body><h1>Menu</h1><main><div id="c12"><h2 data-headliner-edit="tt_content:12:header_tag">Title</h2></div></main></body>';

        self::assertEquals(
            [new Heading(1, 'Menu', outside: true), new Heading(2, 'Title', 'tt_content:12:header_tag', 12)],
            (new HeadingExtractor())->extract($html, 'main')
        );
    }

    #[Test]
    public function takesTheContentElementFromTheMarkOfTheHeading(): void
    {
        // a Content Block has no frame with an id: the headings name their element themselves
        $html = '<body><section><h2 data-headliner-element="82" data-headliner-edit="tt_content:82:header_tag">Cards</h2>'
            . '<article><h3 data-headliner-element="82" data-headliner-edit="cards:7:tag">First</h3></article></section></body>';

        self::assertEquals(
            [new Heading(2, 'Cards', 'tt_content:82:header_tag', 82), new Heading(3, 'First', 'cards:7:tag', 82)],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function theMarkOfTheHeadingWinsOverTheFrameAroundIt(): void
    {
        $html = '<body><div id="c5"><h2 data-headliner-element="82">Inner</h2><h2>Plain</h2></div></body>';

        self::assertEquals(
            [new Heading(2, 'Inner', null, 82), new Heading(2, 'Plain', null, 5)],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function aMarkThatIsNoUidIsIgnored(): void
    {
        $html = '<body><div id="c5"><h2 data-headliner-element="abc">A</h2><h2 data-headliner-element="0">B</h2></div></body>';

        self::assertEquals([new Heading(2, 'A', null, 5), new Heading(2, 'B', null, 5)], (new HeadingExtractor())->extract($html));
    }

    #[Test]
    public function aHeadingInARecordKnowsItsMarkItsRecordAndItsElement(): void
    {
        // a card of a Content Block: the title has the mark of its tag field and its element, the heading in the text
        // names the record and the field
        $html = '<body><section><h3 data-headliner-edit="cards:3:tag" data-headliner-element="82">Title</h3><p>x</p>'
            . '<h4 data-headliner-record="cards:3" data-headliner-field="text">In the text</h4><h2>Not in the card</h2></section></body>';

        self::assertEquals(
            [
                new Heading(3, 'Title', 'cards:3:tag', 82),
                new Heading(4, 'In the text', null, null, false, 'cards:3'),
                new Heading(2, 'Not in the card'),
            ],
            (new HeadingExtractor())->extract($html)
        );
    }

    #[Test]
    public function aHeadingTakesItsElementFromTheNearestMarkOrFrame(): void
    {
        $html = '<body><div id="c5"><div data-headliner-element="82"><h2>A</h2></div><h2>B</h2></div></body>';

        self::assertEquals([new Heading(2, 'A', null, 82), new Heading(2, 'B', null, 5)], (new HeadingExtractor())->extract($html));
    }

    #[Test]
    public function aHeadingInARichTextNamesItsRecordItself(): void
    {
        $html = '<body><h3 data-headliner-record="cards:3" data-headliner-field="text">In the text</h3></body>';

        self::assertEquals([new Heading(3, 'In the text', null, null, false, 'cards:3')], (new HeadingExtractor())->extract($html));
    }

    #[Test]
    public function aRecordMarkOnAnElementAroundAHeadingIsNotRead(): void
    {
        // only the heading says where it is written: nothing that wraps it can (an editor can write to the page)
        $html = '<body><div data-headliner-record="cards:3"><h3>Inner</h3></div></body>';

        self::assertNull((new HeadingExtractor())->extract($html)[0]->record);
    }
}
