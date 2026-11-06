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

    private function render(int $issueCount, string $language): string
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create($language);
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(templateRootPaths: ['EXT:headliner/Resources/Private/Templates']));
        $view->assignMultiple([
            'collapsed' => false,
            'tags' => [],
            'workspace' => '',
            'multiple' => true,
            'columns' => 1,
            'issueCount' => $issueCount,
            'anyLoaded' => true,
            'sections' => [[
                'languageId' => 0,
                'title' => 'English',
                'flag' => 'flags-gb',
                'loaded' => true,
                'untranslated' => false,
                'hidden' => false,
                'publication' => 'published',
                'publicationFrom' => '',
                'publicationUntil' => '',
                'access' => 'open',
                'accessGroups' => '',
                'hasHeadings' => true,
                'headings' => [],
                'pageIssues' => [],
                'issueCount' => $issueCount,
            ]],
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
}
