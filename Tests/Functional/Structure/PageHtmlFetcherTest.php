<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Structure;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\HeadingExtractor;
use Twtgroup\Headliner\Structure\HeadingValidator;
use Twtgroup\Headliner\Structure\PageHtmlFetcher;
use Twtgroup\Headliner\Structure\Simulation;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use Twtgroup\Headliner\Tests\Functional\Fixtures\TestingContextRequestHandler;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Http\RequestHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * The sub-request of the page module, through the real frontend application.
 */
final class PageHtmlFetcherTest extends AbstractFrontendTestCase
{
    /**
     * The real fetcher with the real request handler of the core, only wrapped for the testing framework.
     */
    private function fetcher(): PageHtmlFetcher
    {
        return new PageHtmlFetcher(new TestingContextRequestHandler($this->get(RequestHandler::class)));
    }

    #[Test]
    public function rendersThePageInTheRunningProcessWithTheMarksForThePageModule(): void
    {
        $this->writeSite();
        $backendUser = new \stdClass();
        $GLOBALS['BE_USER'] = $backendUser;

        $html = $this->fetcher()->fetch(new Uri('https://website.local/'));

        self::assertNotNull($html);
        self::assertStringContainsString('data-headliner-edit="tt_content:1:header_tag"', $html);
        // what the frontend changed is put back
        self::assertSame($backendUser, $GLOBALS['BE_USER']);
    }

    #[Test]
    public function theStructureOfThePageIsReadFromIt(): void
    {
        $this->writeSite();

        $html = $this->fetcher()->fetch(new Uri('https://website.local/'));
        $headings = (new HeadingExtractor())->extract((string) $html);

        self::assertNotSame([], $headings);
        // the fixture has an h4, a div header and no h1
        $issueTypes = array_map(static fn($issue): string => $issue->type, (new HeadingValidator())->validate($headings));
        self::assertContains('missingH1', $issueTypes);
        // the editable div is listed as a heading with level 0, with the record behind it
        $levels = array_map(static fn($heading): int => $heading->level, $headings);
        self::assertContains(0, $levels);
        self::assertSame('tt_content:2:header_tag', array_values(array_filter($headings, static fn($heading): bool => $heading->level === 0))[0]->edit);
    }

    #[Test]
    public function aPageThatCannotBeRenderedGivesNothing(): void
    {
        $this->writeSite();

        self::assertNull($this->fetcher()->fetch(new Uri('https://website.local/does-not-exist')));
    }

    #[Test]
    public function rendersAPageThatStartsLaterAtItsStartTimeAndPutsTheTimeBack(): void
    {
        $this->writeSite();
        unset($GLOBALS['SIM_EXEC_TIME'], $GLOBALS['SIM_ACCESS_TIME']);
        $uri = new Uri('https://website.local/starts-later');

        self::assertNull($this->fetcher()->fetch($uri));
        $html = $this->fetcher()->fetch($uri, new Simulation(time: 4102444800));

        self::assertNotNull($html);
        self::assertStringContainsString('Starts later</h2>', $html);
        // the simulated time must not stay behind in the backend request
        self::assertArrayNotHasKey('SIM_EXEC_TIME', $GLOBALS);
        self::assertArrayNotHasKey('SIM_ACCESS_TIME', $GLOBALS);
    }

    #[Test]
    public function rendersAPageForMembersAsAMemberAndPutsTheUserBack(): void
    {
        $this->writeSite();
        $uri = new Uri('https://website.local/members');
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('frontend.user', new UserAspect(null, [0, -1]));

        self::assertNull($this->fetcher()->fetch($uri));
        $html = $this->fetcher()->fetch($uri, new Simulation(groups: [0, -2, 1]));

        self::assertNotNull($html);
        self::assertStringContainsString('For members</h2>', $html);
        // the backend request must not be left as a frontend user
        self::assertSame([0, -1], $context->getPropertyFromAspect('frontend.user', 'groupIds'));
    }

    #[Test]
    public function rendersTheWorkspaceVersionAndPutsTheWorkspaceBack(): void
    {
        $this->writeSite();
        $uri = new Uri('https://website.local/');
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('workspace', new WorkspaceAspect(0));

        $live = $this->fetcher()->fetch($uri);
        $draft = $this->fetcher()->fetch($uri, new Simulation(workspace: 1));

        self::assertNotNull($live);
        self::assertStringContainsString('Chosen tag</h4>', $live);
        self::assertNotNull($draft);
        self::assertStringContainsString('Chosen tag (draft)</h5>', $draft);
        self::assertSame(0, $context->getPropertyFromAspect('workspace', 'id'));
    }

    private const PAGE_WITH_SURROUNDINGS = "page = PAGE\npage.10 < styles.content.get\n"
        . "page.10.stdWrap.wrap = <header><h1>Site name</h1></header><main>|</main><footer><h2>Footer</h2></footer>\n";

    /**
     * @return list<string> The problems of the page as the page module finds them, for a content selector
     */
    private function problems(?string $selector): array
    {
        $html = (string) $this->fetcher()->fetch(new Uri('https://website.local/'));
        $headings = (new HeadingExtractor())->extract($html, $selector);

        return array_map(static fn($issue): string => $issue->type, (new HeadingValidator())->validate($headings));
    }

    #[Test]
    public function theHeadingsOfTheHeaderAndTheFooterAreNotChecked(): void
    {
        $this->writeSite([], self::PAGE_WITH_SURROUNDINGS);
        $html = (string) $this->fetcher()->fetch(new Uri('https://website.local/'));

        $outside = array_map(
            static fn(Heading $heading): string => $heading->text,
            array_values(array_filter((new HeadingExtractor())->extract($html, 'main'), static fn(Heading $heading): bool => $heading->outside))
        );

        self::assertSame(['Site name', 'Footer'], $outside);
    }

    #[Test]
    public function anH1OutsideOfTheContentCountsAsTheH1OfThePage(): void
    {
        $this->writeSite([], self::PAGE_WITH_SURROUNDINGS);

        // the content has no h1, the header has one: the page has an h1, although the editor cannot change it
        self::assertNotContains('missingH1', $this->problems('main'));
        self::assertNotContains('missingH1', $this->problems(null));
    }

    #[Test]
    public function aPageWithoutMainIsCheckedAsAWhole(): void
    {
        $this->writeSite();

        // no <main> in the page: the selector matches nothing, the whole page is the content
        self::assertSame($this->problems(null), $this->problems('main'));
    }

    #[Test]
    public function theSiteSettingForTheContentIsEmptyByDefaultAndCanBeChanged(): void
    {
        $this->writeSite();
        $default = $this->get(SiteFinder::class)->getSiteByIdentifier('test')->getSettings()->get('headliner.contentSelector');

        $this->writeSite(['headliner' => ['contentSelector' => '#content']]);
        $changed = $this->get(SiteFinder::class)->getSiteByIdentifier('test')->getSettings()->get('headliner.contentSelector');

        // empty: the whole page is checked
        self::assertSame('', $default);
        self::assertSame('#content', $changed);
    }
}
