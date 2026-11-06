<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Twtgroup\Headliner\Event\ModifyHeadingIssuesEvent;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The box with the heading structure, as the page module loads it.
 */
final class StructureBoxTest extends AbstractFrontendTestCase
{
    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->writeSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['EXEC_TIME'] = time();
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

    #[Test]
    public function aStandardPageOfASiteHasABox(): void
    {
        self::assertTrue($this->structureBox()->supports(1));
    }

    #[Test]
    public function aPageThatDoesNotExistHasNoBox(): void
    {
        self::assertFalse($this->structureBox()->supports(9999));
    }

    #[Test]
    public function drawsTheStructureOfThePageWithItsProblemsAndTheReturnUrl(): void
    {
        $html = $this->structureBox()->render(
            $this->backendRequest(),
            1,
            [0],
            '/typo3/module/web/layout?id=1'
        );

        self::assertStringContainsString('class="headliner card', $html);
        self::assertStringContainsString('data-headliner-edit="tt_content:1:header_tag"', $html);
        // the fixture has no h1
        self::assertStringContainsString('The page has no H1.', $html);
        // the edit forms return to the page module, not to the AJAX route that has drawn the box
        self::assertStringContainsString('returnUrl=/typo3/module/web/layout?id%3D1', $html);
        self::assertStringNotContainsString('headliner/structure', $html);
    }

    #[Test]
    public function aPageWithoutABoxGivesNothing(): void
    {
        $html = $this->structureBox()->render(
            $this->backendRequest(),
            9999,
            [0],
            ''
        );

        self::assertSame('', $html);
    }

    #[Test]
    public function aHeadingFromTheTextCanBeChangedInTheBoxWhereTheFieldSaysItIs(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);
        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '/typo3/module/web/layout?id=8');

        // the headings of the text: place 0 and 1 in the text of element 21, without div as a choice
        self::assertStringContainsString('data-headliner-rte="tt_content:21:bodytext:0"', $html);
        self::assertStringContainsString('data-headliner-rte="tt_content:21:bodytext:1"', $html);
        self::assertStringContainsString('data-headliner-text="Opening hours"', $html);
        // the select of the header offers every tag, the two selects of the text only what the editor offers (h2, h3)
        self::assertSame(1, substr_count($html, '<option value="div"'));
        self::assertSame(1, substr_count($html, '<option value="h4"'));
        self::assertSame(3, substr_count($html, '<option value="h3"'));
        // the header of the element has its own tag field, not the text
        self::assertStringContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
    }

    #[Test]
    public function aHeadingFromTheTextIsOnlyALabelForAUserWhoMayNotEditTheElement(): void
    {
        $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringNotContainsString('data-headliner-rte="tt_content:21:', $html);
        self::assertStringNotContainsString('<select', $html);
    }

    private function pageTsConfig(int $pageId, string $tsConfig): void
    {
        $this->get(\TYPO3\CMS\Core\Database\ConnectionPool::class)->getConnectionForTable('pages')
            ->update('pages', ['TSconfig' => $tsConfig], ['uid' => $pageId]);
        \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
    }

    #[Test]
    public function aTagFieldThatThePageTsConfigHidesIsNotChangedInTheBox(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);
        $this->pageTsConfig(8, 'TCEFORM.tt_content.header_tag.disabled = 1');

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        // the form has no field for it, so the box has no select; the headings of the text are not affected
        self::assertStringNotContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
        self::assertStringContainsString('data-headliner-rte="tt_content:21:bodytext:0"', $html);
    }

    #[Test]
    public function aTagFieldHiddenForTheTypeOfTheElementIsNotChangedInTheBox(): void
    {
        $this->pageTsConfig(8, 'TCEFORM.tt_content.header_tag.types.text.disabled = 1');

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringNotContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
    }

    #[Test]
    public function aTagFieldHiddenForAnotherTypeIsStillChangedInTheBox(): void
    {
        $this->pageTsConfig(8, 'TCEFORM.tt_content.header_tag.types.textpic.disabled = 1');

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
    }

    #[Test]
    public function aTypeCanShowAgainATagFieldThatIsHiddenForAllTypes(): void
    {
        $this->pageTsConfig(8, "TCEFORM.tt_content.header_tag.disabled = 1\nTCEFORM.tt_content.header_tag.types.text.disabled = 0");

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
    }

    #[Test]
    public function theHeadingsOfTheTextAreNotChangedInTheBoxIfThePageTsConfigHidesTheTextField(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);
        $this->pageTsConfig(8, 'TCEFORM.tt_content.bodytext.disabled = 1');

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringNotContainsString('data-headliner-rte="tt_content:21:bodytext', $html);
        // the tag field of the header is not affected
        self::assertStringContainsString('data-headliner-edit="tt_content:21:header_tag"', $html);
    }

    #[Test]
    public function aHeadingFromTheTextIsOnlyALabelWhileTheSiteSettingIsOff(): void
    {
        // the setting is off by default
        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        self::assertStringNotContainsString('data-headliner-rte="tt_content:21:', $html);
        // only the select of the header of the element is left
        self::assertSame(1, substr_count($html, '<select'));
    }

    #[Test]
    public function theHeadingsOfOneElementAreMarkedAsAGroup(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);

        $html = $this->structureBox()->render($this->backendRequest(), 8, [0], '');

        // element 21: its header and the two headings of its text, one run of three rows
        self::assertSame(3, substr_count($html, 'data-headliner-group="21"'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-start'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-middle'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-end'));
    }

    #[Test]
    public function aHeadingThatIsAloneInItsElementHasNoGroup(): void
    {
        // page 1: the elements have one heading each, except the one with a subheader
        $html = $this->structureBox()->render($this->backendRequest(), 1, [0], '');

        self::assertSame(1, substr_count($html, 'headliner__item--group-start'));
        self::assertSame(1, substr_count($html, 'headliner__item--group-end'));
        self::assertSame(0, substr_count($html, 'headliner__item--group-middle'));
    }

    /**
     * A dispatcher that hands the event of the issues to the listener of the test.
     *
     * @param callable(ModifyHeadingIssuesEvent): void $listener
     */
    private function dispatcher(callable $listener): EventDispatcherInterface
    {
        return new class ($listener) implements EventDispatcherInterface {
            /** @param callable(ModifyHeadingIssuesEvent): void $listener */
            public function __construct(private $listener) {}

            public function dispatch(object $event): object
            {
                if ($event instanceof ModifyHeadingIssuesEvent) {
                    ($this->listener)($event);
                }

                return $event;
            }
        };
    }

    #[Test]
    public function aListenerCanAddIssuesToTheBox(): void
    {
        $html = $this->structureBox(null, $this->dispatcher(static function (ModifyHeadingIssuesEvent $event): void {
            self::assertSame(1, $event->getPageId());
            self::assertSame(0, $event->getLanguageId());
            self::assertNotSame([], $event->getHeadings());
            $event->addIssue('myext.noLead', null, 'No lead paragraph');
            $event->addIssue('myext.tooLong', 0, 'Too long for a heading');
        }))->render($this->backendRequest(), 1, [0], '');

        // one of the page, one of the first heading: shown as the other issues are
        self::assertStringContainsString('No lead paragraph', $html);
        self::assertStringContainsString('Too long for a heading', $html);
        self::assertSame(1, substr_count($html, 'Too long for a heading'));
    }

    #[Test]
    public function aListenerThatThrowsDoesNotBreakTheBox(): void
    {
        $logger = new class extends \Psr\Log\AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $html = $this->structureBox(null, $this->dispatcher(static function (ModifyHeadingIssuesEvent $event): void {
            // what a listener that does half of its work and then fails leaves behind must not be shown either
            $event->addIssue('myext.half', null, 'Half done');
            throw new \RuntimeException('The listener is broken', 1730900000);
        }), $logger)->render($this->backendRequest(), 1, [0], '');

        // the box is drawn with what the built-in rules found (the fixture has no h1)
        self::assertStringContainsString('class="headliner card', $html);
        self::assertStringContainsString('The page has no H1.', $html);
        self::assertStringNotContainsString('Half done', $html);
        // and the failure is logged, with the exception, for the developer of the listener
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertInstanceOf(\RuntimeException::class, $logger->records[0]['context']['exception']);
    }

    #[Test]
    public function aListenerCanTakeABuiltInRuleAway(): void
    {
        $withRule = $this->structureBox()->render($this->backendRequest(), 1, [0], '');
        $without = $this->structureBox(null, $this->dispatcher(static function (ModifyHeadingIssuesEvent $event): void {
            $event->removeIssues('missingH1');
        }))->render($this->backendRequest(), 1, [0], '');

        // the fixture has no h1
        self::assertStringContainsString('The page has no H1.', $withRule);
        self::assertStringNotContainsString('The page has no H1.', $without);
    }

    #[Test]
    public function theIssuesOfAListenerAreCountedInTheSummary(): void
    {
        $count = static fn(string $html): int => (int) (preg_match('/(\d+) issues?/', $html, $match) === 1 ? $match[1] : 0);

        $before = $count($this->structureBox()->render($this->backendRequest(), 1, [0], ''));
        $after = $count($this->structureBox(null, $this->dispatcher(static function (ModifyHeadingIssuesEvent $event): void {
            $event->addIssue('myext.a', null, 'A');
            $event->addIssue('myext.b', 0, 'B');
        }))->render($this->backendRequest(), 1, [0], ''));

        self::assertSame($before + 2, $after);
    }

    #[Test]
    public function aLabelCanBeAnLllReferenceAndIsEscapedAsText(): void
    {
        $html = $this->structureBox(null, $this->dispatcher(static function (ModifyHeadingIssuesEvent $event): void {
            $event->addIssue('myext.ref', null, 'LLL:EXT:headliner/Resources/Private/Language/locallang.xlf:untranslated');
            $event->addIssue('myext.markup', null, '<img src=x onerror=alert(1)>');
        }))->render($this->backendRequest(), 1, [0], '');

        self::assertStringNotContainsString('LLL:EXT:headliner', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    #[Test]
    public function anIssueThatNamesAHeadingThatIsNotThereIsIgnored(): void
    {
        $count = static fn(string $html): int => (int) (preg_match('/(\d+) issues?/', $html, $match) === 1 ? $match[1] : 0);

        $before = $count($this->structureBox()->render($this->backendRequest(), 1, [0], ''));
        $html = $this->structureBox(null, $this->dispatcher(static function (ModifyHeadingIssuesEvent $event): void {
            $event->addIssue('myext.nowhere', 999, 'Nowhere');
            $event->addIssue('myext.negative', -1, 'Negative');
            $event->addIssue('', null, 'Without a name');
        }))->render($this->backendRequest(), 1, [0], '');

        self::assertStringNotContainsString('Nowhere', $html);
        self::assertStringNotContainsString('Negative', $html);
        self::assertStringNotContainsString('Without a name', $html);
        self::assertSame($before, $count($html));
    }
}
