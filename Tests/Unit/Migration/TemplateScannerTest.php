<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Migration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Migration\DecisionReason;
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
        self::assertSame('<headliner:headline record="{record}" default="h2" />', $found[0]->suggestion);
    }

    #[Test]
    public function theTagOfTheTemplateIsKeptAsTheDefaultSoTheOutputStaysTheSame(): void
    {
        $found = $this->scanner->scan("<h1 class=\"stage\">{data -> f:render.text(field: 'header')}</h1>");

        self::assertSame('<headliner:headline record="{data}" default="h1" class="stage" />', $found[0]->suggestion);
    }

    #[Test]
    public function theWholeElementIsKeptSoItCanBeShownAsTheOldPart(): void
    {
        $found = $this->scanner->scan("<h3 class=\"card\">\n    {card -> f:render.text(field: 'title')}\n</h3>");

        self::assertSame("<h3 class=\"card\"> {card -> f:render.text(field: 'title')} </h3>", $found[0]->original);
    }

    #[Test]
    public function aFieldOtherThanTheHeaderIsNamedAsTextField(): void
    {
        $found = $this->scanner->scan("<h3 class=\"card\">{card -> f:render.text(field: 'title')}</h3>");

        self::assertSame(HeadingKind::Migratable, $found[0]->kind);
        self::assertSame('<headliner:headline record="{card}" textField="title" default="h3" class="card" />', $found[0]->suggestion);
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
        self::assertSame('<headliner:headline record="{record}" default="h2" />', $found[0]->suggestion);
    }

    #[Test]
    public function aPageTitleNeedsADecisionBecauseAPageHasNoTag(): void
    {
        $found = $this->scanner->scan("<h1>{page -> f:render.text(field: 'title')}</h1>");

        self::assertSame(HeadingKind::NeedsDecision, $found[0]->kind);
        self::assertNull($found[0]->suggestion);
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

    #[Test]
    public function aSwitchOfTagsForTheSameTextIsOneComponentFinding(): void
    {
        $source = "<f:switch expression=\"{level}\">\n"
            . "<f:case value=\"1\"><h1 class=\"{class}\">{content}</h1></f:case>\n"
            . "<f:case value=\"2\"><h2 class=\"{class}\">{content}</h2></f:case>\n"
            . "<f:case value=\"3\"><h3 class=\"{class}\">{content}</h3></f:case>\n"
            . "</f:switch>\n<h4>{data.header}</h4>";
        $found = $this->scanner->scan($source);

        self::assertCount(2, $found);
        self::assertSame(HeadingKind::Component, $found[0]->kind);
        self::assertSame(2, $found[0]->line);
        self::assertSame('h1/h2/h3', $found[0]->tag);
        self::assertSame('class="{class}"', $found[0]->attributes);
        self::assertSame(3, $found[0]->count);
        self::assertSame('<headliner:headline.values tag="{tag}" class="{class}">{content}</headliner:headline.values>', $found[0]->suggestion);
        self::assertSame(HeadingKind::Migratable, $found[1]->kind, 'the heading after the switch is its own finding');
    }

    #[Test]
    public function headingsThatOnlyLookAlikeAreNoSwitch(): void
    {
        // the same text in two places with the same tag, or in different places of the template: no switch
        self::assertSame(HeadingKind::Static, $this->scanner->scan('<h2>A</h2><h2>A</h2><h2>A</h2>')[0]->kind);
        $found = $this->scanner->scan("<h2>{x}</h2><p>text</p><h3>{x}</h3><p>text</p><h4>{x}</h4>");
        self::assertCount(3, $found);
    }

    #[Test]
    public function theTemplatesOfTheBackendAreRecognizedByTheirPath(): void
    {
        self::assertTrue($this->scanner->isBackendTemplate('packages/x/ContentBlocks/ContentElements/card/templates/backend-preview.html'));
        self::assertTrue($this->scanner->isBackendTemplate('ext/Resources/Private/Backend/Templates/List.html'));
        self::assertFalse($this->scanner->isBackendTemplate('ext/Resources/Private/Templates/Backend.html'));
        self::assertFalse($this->scanner->isBackendTemplate('ext/ContentBlocks/ContentElements/card/templates/frontend.html'));
    }

    /**
     * @return iterable<string, array{string, DecisionReason}>
     */
    public static function reasons(): iterable
    {
        yield 'translate tag' => ['<h2><f:translate key="a" /></h2>', DecisionReason::Translation];
        yield 'translate inline' => ["<h2>{f:translate(key: 'a')}</h2>", DecisionReason::Translation];
        yield 'LLL' => ['<h2>LLL:EXT:x/a.xlf:t</h2>', DecisionReason::Translation];
        yield 'stripTags of a record field' => ["<h2>{data.tx_x_headline -> f:format.stripTags(allowedTags: '<span>')}</h2>", DecisionReason::FormattedRecordField];
        yield 'raw around a record field' => ['<h2><f:format.raw>{data.header}</f:format.raw></h2>', DecisionReason::FormattedRecordField];
        yield 'nl2br of a record field' => ['<h2>{record.title -> f:format.nl2br()}</h2>', DecisionReason::FormattedRecordField];
        yield 'raw around another variable' => ['<h2><f:format.raw>{newsItem.title}</f:format.raw></h2>', DecisionReason::Formatted];
        yield 'link' => ['<h2><a href="{u}">{t}</a></h2>', DecisionReason::Link];
        yield 'typolink with format' => ['<h2><f:link.typolink parameter="{l}">{header -> f:format.raw()}</f:link.typolink></h2>', DecisionReason::Link];
        yield 'condition' => ['<h2><f:if condition="{x}">{y}</f:if></h2>', DecisionReason::Condition];
        yield 'render.text in a condition' => ["<h2><f:if condition=\"{data.header}\"><f:then>{data -> f:render.text(field: 'header')}</f:then></f:if></h2>", DecisionReason::Condition];
        yield 'partial' => ['<h2><f:render partial="X" /></h2>', DecisionReason::Partial];
        yield 'single variable' => ['<h2>{newsItem.title}</h2>', DecisionReason::Variable];
        yield 'markup' => ['<h2><strong>{x}</strong></h2>', DecisionReason::Markup];
        yield 'several variables' => ['<h2>{a} {b}</h2>', DecisionReason::Mixed];
    }

    #[Test]
    #[DataProvider('reasons')]
    public function aDecisionSaysWhy(string $template, DecisionReason $expected): void
    {
        $found = $this->scanner->scan($template);

        self::assertSame(HeadingKind::NeedsDecision, $found[0]->kind);
        self::assertSame($expected, $found[0]->reason);
    }

    #[Test]
    public function onlyADecisionHasAReason(): void
    {
        $found = $this->scanner->scan('<h2>{data.header}</h2><h3>Fixed</h3>');

        self::assertNull($found[0]->reason);
        self::assertNull($found[1]->reason);
    }
}
