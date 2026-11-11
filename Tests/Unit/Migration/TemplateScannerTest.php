<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Migration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Migration\HeadingKind;
use Twtgroup\Headliner\Migration\TemplateScanner;

final class TemplateScannerTest extends TestCase
{
    private TemplateScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new TemplateScanner();
    }

    #[Test]
    public function aFileWithoutFluidIsNoTemplate(): void
    {
        self::assertFalse($this->scanner->isFluid('<h2>Title</h2>'));
        self::assertTrue($this->scanner->isFluid('<f:render partial="X" />'));
        self::assertTrue($this->scanner->isFluid('<html data-namespace-typo3-fluid="true"></html>'));
        self::assertTrue($this->scanner->isFluid('{namespace x=Vendor\X\ViewHelpers}'));
    }

    #[Test]
    public function aHeadingWithRenderTextIsMigratableAndGetsTheViewHelper(): void
    {
        $found = $this->scanner->scan("<f:if condition=\"{x}\">\n<h2>{record -> f:render.text(field: 'header')}</h2></f:if>");

        self::assertCount(1, $found);
        self::assertSame(HeadingKind::Migratable, $found[0]->kind);
        self::assertSame(2, $found[0]->line);
        self::assertSame('h2', $found[0]->tag);
        self::assertSame('<headliner:headline record="{record}" />', $found[0]->suggestion);
    }

    #[Test]
    public function aFieldOtherThanTheHeaderIsNamedAsTextField(): void
    {
        $found = $this->scanner->scan("<h3 class=\"card\">{card -> f:render.text(field: 'title')}</h3>");

        self::assertSame(HeadingKind::Migratable, $found[0]->kind);
        self::assertSame('<headliner:headline record="{card}" textField="title" />', $found[0]->suggestion);
        self::assertSame('class="card"', $found[0]->attributes);
    }

    #[Test]
    public function theSubheaderFieldBecomesTheSubheaderSwitch(): void
    {
        $found = $this->scanner->scan("<h3>{record -> f:render.text(field: 'subheader')}</h3>");

        self::assertSame('<headliner:headline record="{record}" subheader="1" />', $found[0]->suggestion);
    }

    #[Test]
    public function aValueOfTheRecordIsMigratableToo(): void
    {
        $found = $this->scanner->scan('<h2>{data.header}</h2>');

        self::assertSame(HeadingKind::Migratable, $found[0]->kind);
        self::assertSame('<headliner:headline record="{record}" />', $found[0]->suggestion);
    }

    #[Test]
    public function translatedFormattedAndOtherDynamicHeadingsNeedADecision(): void
    {
        $found = $this->scanner->scan(
            '<h2><f:translate key="LLL:EXT:x/a.xlf:t" /></h2>'
            . '<h2><f:format.html>{x}</f:format.html></h2>'
            . '<h2>{title} ({count})</h2>'
        );

        self::assertCount(3, $found);
        foreach ($found as $heading) {
            self::assertSame(HeadingKind::NeedsDecision, $heading->kind);
            self::assertNull($heading->suggestion);
        }
    }

    #[Test]
    public function aStaticHeadingIsStatic(): void
    {
        $found = $this->scanner->scan('<h1>Welcome</h1>');

        self::assertSame(HeadingKind::Static, $found[0]->kind);
    }

    #[Test]
    public function aMultilineHeadingIsFoundAndItsContentIsOneLine(): void
    {
        $found = $this->scanner->scan("<h2>\n    {record -> f:render.text(field: 'header')}\n</h2>");

        self::assertSame(HeadingKind::Migratable, $found[0]->kind);
        self::assertSame("{record -> f:render.text(field: 'header')}", $found[0]->text);
    }

    #[Test]
    public function commentsAreSkippedAndKeepTheLineNumbers(): void
    {
        $found = $this->scanner->scan("<!-- <h2>{data.header}</h2>\n-->\n<f:comment><h3>{x}</h3></f:comment>\n<h4>{data.header}</h4>");

        self::assertCount(1, $found);
        self::assertSame(4, $found[0]->line);
    }

    #[Test]
    public function anHtmlTagThatOnlyStartsWithHIsNoHeading(): void
    {
        self::assertSame([], $this->scanner->scan('<header>{data.header}</header><hr /><h7>x</h7>'));
    }
}
