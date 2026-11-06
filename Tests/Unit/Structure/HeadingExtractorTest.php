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
}
