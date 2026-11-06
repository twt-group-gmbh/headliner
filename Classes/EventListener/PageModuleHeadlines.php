<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Twtgroup\Headliner\Structure\HeadingExtractor;
use Twtgroup\Headliner\Structure\HeadingValidator;
use Twtgroup\Headliner\Structure\PageHtmlFetcher;

/**
 * Shows the heading structure of the page, with its problems, above the content of the page module.
 */
#[AsEventListener(identifier: 'headliner/page-module-headlines')]
final readonly class PageModuleHeadlines
{
    /** Standard pages; folders, links, spacers etc. render no content. */
    private const DOKTYPE_STANDARD = 1;

    public function __construct(
        private SiteFinder $siteFinder,
        private PageHtmlFetcher $fetcher,
        private HeadingExtractor $extractor,
        private HeadingValidator $validator,
        private ViewFactoryInterface $viewFactory,
        private PageRenderer $pageRenderer,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageId = (int) ($request->getQueryParams()['id'] ?? 0);
        $page = $pageId > 0 ? BackendUtility::getRecord('pages', $pageId, 'doktype') : null;
        if ($page === null || (int) $page['doktype'] !== self::DOKTYPE_STANDARD) {
            return;
        }

        try {
            $uri = $this->siteFinder->getSiteByPageId($pageId)->getRouter()->generateUri(
                $pageId,
                ['_language' => $this->getLanguageId($request)]
            );
        } catch (SiteNotFoundException) {
            return;
        }

        $html = $this->fetcher->fetch($uri);
        $headings = $html === null ? [] : $this->extractor->extract($html);
        $issues = $this->validator->validate($headings);

        $this->pageRenderer->addCssFile('EXT:headliner/Resources/Public/Css/PageModule.css');

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:headliner/Resources/Private/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'loaded' => $html !== null,
            'headings' => $this->buildRows($headings, $issues),
            'pageIssues' => array_values(array_filter($issues, static fn($issue) => $issue->index === null)),
        ]);
        $event->addHeaderContent($view->render('PageModule/Headlines'));
    }

    /**
     * @param list<\Twtgroup\Headliner\Structure\Heading> $headings
     * @param list<\Twtgroup\Headliner\Structure\Issue> $issues
     * @return list<array{level: int, text: string, issues: list<string>}>
     */
    private function buildRows(array $headings, array $issues): array
    {
        $rows = [];
        foreach ($headings as $index => $heading) {
            $rows[] = [
                'level' => $heading->level,
                'text' => $heading->text,
                'issues' => array_values(array_map(
                    static fn($issue): string => $issue->type->value,
                    array_filter($issues, static fn($issue): bool => $issue->index === $index)
                )),
            ];
        }

        return $rows;
    }

    private function getLanguageId(ServerRequestInterface $request): int
    {
        $moduleData = $request->getAttribute('moduleData');
        $languages = $moduleData instanceof ModuleData ? $moduleData->get('languages') : null;
        $first = is_array($languages) ? reset($languages) : false;

        return $first !== false && (int) $first > 0 ? (int) $first : 0;
    }
}
