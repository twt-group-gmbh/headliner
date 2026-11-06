<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Structure\RteHeadings;

final class RteHeadingsTest extends TestCase
{
    private const HTML = '<h2>Visit us</h2><p>Main street 1</p><h3 class="x">Opening <strong>hours</strong></h3><p>text</p><h2>Write &amp; call</h2>';

    #[Test]
    public function listsTheHeadingsInTheOrderOfTheText(): void
    {
        self::assertSame(
            [['level' => 2, 'text' => 'Visit us'], ['level' => 3, 'text' => 'Opening hours'], ['level' => 2, 'text' => 'Write & call']],
            RteHeadings::all(self::HTML)
        );
    }

    #[Test]
    public function normalisesWhitespaceAndNonBreakingSpaces(): void
    {
        self::assertSame([['level' => 1, 'text' => 'A B C']], RteHeadings::all("<h1>A\n  B&nbsp;C</h1>"));
    }

    #[Test]
    public function aCommentIsNoHeading(): void
    {
        // it is not rendered, so it has no place in the order of the rendered headings
        self::assertSame([['level' => 2, 'text' => 'Real']], RteHeadings::all('<!-- <h2>Old</h2> --><h2>Real</h2>'));
    }

    #[Test]
    public function changesTheTagOfOneHeadingAndKeepsEverythingElse(): void
    {
        $changed = RteHeadings::withLevel(self::HTML, 1, 4);

        self::assertSame('<h2>Visit us</h2><p>Main street 1</p><h4 class="x">Opening <strong>hours</strong></h4><p>text</p><h2>Write &amp; call</h2>', $changed);
    }

    #[Test]
    public function changesTheLastHeadingToo(): void
    {
        self::assertSame(
            '<h2>Visit us</h2><p>Main street 1</p><h3 class="x">Opening <strong>hours</strong></h3><p>text</p><h5>Write &amp; call</h5>',
            RteHeadings::withLevel(self::HTML, 2, 5)
        );
    }

    #[Test]
    public function anUnknownHeadingChangesNothing(): void
    {
        self::assertNull(RteHeadings::withLevel(self::HTML, 3, 2));
        self::assertNull(RteHeadings::withLevel(self::HTML, -1, 2));
    }

    #[Test]
    public function onlyLevelsOneToSixAreAllowed(): void
    {
        self::assertNull(RteHeadings::withLevel(self::HTML, 0, 7));
        self::assertNull(RteHeadings::withLevel(self::HTML, 0, 0));
    }

    #[Test]
    public function aHeadingWithoutAClosingTagIsLeftAlone(): void
    {
        self::assertNull(RteHeadings::withLevel('<h2>Open', 0, 3));
    }

    #[Test]
    public function readsTheHeadingsTheEditorOffersFromItsOptions(): void
    {
        // the options of the default preset of the RTE: paragraph, h2, h3 and a formatted text
        $options = [
            ['model' => 'paragraph', 'title' => 'Paragraph'],
            ['model' => 'heading2', 'view' => 'h2', 'title' => 'Heading 2'],
            ['model' => 'heading3', 'view' => 'h3', 'title' => 'Heading 3'],
            ['model' => 'formatted', 'view' => 'pre', 'title' => 'Pre-Formatted Text'],
        ];

        self::assertSame(['h2', 'h3'], RteHeadings::tagsFromHeadingOptions($options));
    }

    #[Test]
    public function readsTheViewAsAnArrayOrTheModelAlone(): void
    {
        $options = [
            ['model' => 'heading4', 'view' => ['name' => 'h4', 'classes' => 'small']],
            ['model' => 'heading1'],
            ['model' => 'heading4', 'view' => 'h4'],
            ['model' => 'heading9', 'view' => 'h9'],
        ];

        // once each, in the order of the levels
        self::assertSame(['h1', 'h4'], RteHeadings::tagsFromHeadingOptions($options));
    }

    #[Test]
    public function withoutOptionsTheEditorOffersAllSixHeadings(): void
    {
        // the default of the editor
        self::assertSame(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], RteHeadings::tagsFromHeadingOptions(null));
    }

    #[Test]
    public function optionsWithoutAHeadingOfferNone(): void
    {
        self::assertSame([], RteHeadings::tagsFromHeadingOptions([['model' => 'paragraph']]));
    }
}
