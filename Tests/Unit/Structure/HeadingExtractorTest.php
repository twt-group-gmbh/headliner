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
}
