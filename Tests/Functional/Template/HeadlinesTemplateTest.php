<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The Fluid template of the box, rendered with the data the page module gives it.
 */
final class HeadlinesTemplateTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
    }

    protected function tearDown(): void
    {
        if ($this->originalLang === []) {
            unset($GLOBALS['LANG']);
        } else {
            $GLOBALS['LANG'] = $this->originalLang['LANG'];
        }
        parent::tearDown();
    }

    /**
     * @param list<array<string, mixed>> $headings
     * @param array<string, mixed> $page Values of the box
     * @param array<string, mixed> $section Values of its section
     */
    private function render(int $issueCount, string $language, array $headings = [], array $page = [], array $section = []): string
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create($language);
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(templateRootPaths: ['EXT:headliner/Resources/Private/Templates']));
        $view->assignMultiple([
            'collapsed' => false,
            'rteUrl' => '/typo3/ajax/headliner/rte-tag',
            'workspace' => $page['workspace'] ?? '',
            'multiple' => true,
            'columns' => 1,
            'issueCount' => $issueCount,
            'anyLoaded' => true,
            'sections' => [array_replace([
                'languageId' => 0,
                'title' => 'English',
                'flag' => 'flags-gb',
                'loaded' => true,
                'untranslated' => false,
                'publication' => 'published',
                'access' => 'open',
                'hasHeadings' => true,
                'headings' => $headings,
                'pageIssues' => [],
                'issueCount' => $issueCount,
            ], $section)],
        ]);

        return $view->render('PageModule/Headlines');
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function countsProvider(): iterable
    {
        yield 'one' => [1, 'default', '1 issue'];
        yield 'several' => [2, 'default', '2 issues'];
        yield 'many' => [12, 'default', '12 issues'];
    }

    #[Test]
    #[DataProvider('countsProvider')]
    public function countsTheProblemsInTheCorrectNumber(int $count, string $language, string $expected): void
    {
        $html = $this->render($count, $language);

        // in the header of the box and in the section of the language
        self::assertSame(2, preg_match_all('#<span class="badge badge-warning">\s*' . preg_quote($expected, '#') . '\s*</span>#', $html), $html);
        self::assertStringNotContainsString('(e)', $html);
        self::assertStringNotContainsString('(s)', $html);
    }

    #[Test]
    public function saysThatThereIsNothingToFixIfThereAreNoProblems(): void
    {
        self::assertStringContainsString('Structure OK', $this->render(0, 'default'));
    }

    #[Test]
    public function hasTheGermanTextsInTheSingularAndThePlural(): void
    {
        $german = $this->get(LanguageServiceFactory::class)->create('de');
        $label = static fn(string $key): string => $german->sL('LLL:EXT:headliner/Resources/Private/Language/locallang.xlf:' . $key);

        self::assertSame('1 Hinweis', $label('summary.issue'));
        self::assertSame('3 Hinweise', sprintf($label('summary.issues'), 3));
        self::assertSame('Struktur in Ordnung', $label('summary.ok'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function heading(bool $canEditTag, bool $tagInherited, bool $subheader = false, bool $fromText = false): array
    {
        return [
            'tag' => 'h2', 'level' => 2, 'text' => 'Title', 'issues' => [], 'edit' => 'tt_content:5:header_tag', 'childLabel' => '', 'group' => null, 'groupPosition' => '', 'groupHue' => 0, 'rte' => null, 'tagOptions' => ['h1', 'h2', 'div'], 'editUid' => 5,
            'canOpen' => true, 'canEditTag' => $canEditTag, 'outside' => false, 'subheader' => $subheader, 'fromText' => $fromText,
            'tagInherited' => $tagInherited,
        ];
    }

    #[Test]
    public function aTagTakenFromTheOriginalIsALabelWithATooltip(): void
    {
        $html = $this->render(0, 'default', [self::heading(false, true)]);

        self::assertStringNotContainsString('<select', $html);
        self::assertMatchesRegularExpression('#<span class="headliner__pill"[^>]*data-current="h2"#', $html);
        self::assertStringContainsString('title="The tag comes from the original of the default language and is changed there."', $html);
    }

    #[Test]
    public function anEditableTagIsASelectWithoutThatTooltip(): void
    {
        $html = $this->render(0, 'default', [self::heading(true, false)]);

        self::assertStringContainsString('<select', $html);
        self::assertStringNotContainsString('comes from the original', $html);
    }

    #[Test]
    public function aSubheaderAndAHeadingFromTheTextHaveShortBadges(): void
    {
        $html = $this->render(0, 'default', [self::heading(false, false, subheader: true), self::heading(false, false, fromText: true)]);

        // the group of the element shows what belongs together, so the badges only say what the heading is
        self::assertMatchesRegularExpression('#<span class="badge badge-secondary">\s*Subheader\s*</span>#', $html);
        self::assertMatchesRegularExpression('#<span class="badge badge-secondary">\s*RTE\s*</span>#', $html);
        self::assertStringNotContainsString('Subheader of', $html);
        self::assertStringNotContainsString('From the text', $html);
    }

    #[Test]
    public function aHeaderHasNoBadgeOfThatKind(): void
    {
        $html = $this->render(0, 'default', [self::heading(false, false)]);

        self::assertDoesNotMatchRegularExpression('#<span class="badge badge-secondary">\s*RTE\s*</span>#', $html);
        self::assertDoesNotMatchRegularExpression('#<span class="badge badge-secondary">\s*Subheader#', $html);
    }

    /** The strings that are no text if they are taken for markup */
    private const EVIL = '<img src=x onerror=alert(1)> "q" \'a\' & <b>bold</b>';

    private static function assertNoMarkup(string $html): void
    {
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringNotContainsString('onerror=alert(1)>', $html);
        self::assertStringNotContainsString('<b>bold</b>', $html);
        // it is there, as text
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    #[Test]
    public function theTextOfAHeadingIsEscapedWithAndWithoutALink(): void
    {
        // the text comes from the page, which an editor writes: an entity in the page is a "<" here
        $withoutLink = self::heading(false, false);
        $withLink = self::heading(true, false);
        foreach ([$withoutLink, $withLink] as $heading) {
            $heading['text'] = self::EVIL;
            $heading['canOpen'] = $heading['canEditTag'];
            self::assertNoMarkup($this->render(0, 'default', [$heading]));
        }
    }

    #[Test]
    public function theTextOfAnEmptyHeadingIsTheNoteAndNothingElse(): void
    {
        $heading = self::heading(false, false);
        $heading['text'] = '';

        self::assertStringContainsString('<em>(empty heading)</em>', $this->render(0, 'default', [$heading]));
    }

    #[Test]
    public function theTitlesThatComeFromTheDatabaseAreEscaped(): void
    {
        $html = $this->render(
            0,
            'default',
            [],
            ['workspace' => self::EVIL],
            [
                'title' => self::EVIL,
            ]
        );

        self::assertNoMarkup($html);
    }

    #[Test]
    public function theBadgeOfATableIsEscaped(): void
    {
        $heading = self::heading(false, false);
        $heading['childLabel'] = self::EVIL;

        self::assertNoMarkup($this->render(0, 'default', [$heading]));
    }
}
