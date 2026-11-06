<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\PageViewMode;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Twtgroup\Headliner\Backend\EditAccess;
use Twtgroup\Headliner\Backend\SelectedLanguages;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Structure\Heading;
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
        $page = $pageId > 0 ? BackendUtility::getRecord('pages', $pageId) : null;
        if ($page === null || (int) $page['doktype'] !== self::DOKTYPE_STANDARD) {
            return;
        }
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $editAccess = new EditAccess($backendUser instanceof BackendUserAuthentication ? $backendUser : null, $page);

        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return;
        }

        $sections = [];
        foreach ($this->getLanguageIds($request) as $languageId) {
            $section = $this->buildSection($site, $page, $languageId, $editAccess);
            if ($section !== null) {
                $sections[] = $section;
            }
        }
        if ($sections === []) {
            return;
        }

        $this->pageRenderer->addCssFile('EXT:headliner/Resources/Public/Css/PageModule.css');
        $this->pageRenderer->loadJavaScriptModule('@twtgroup/headliner/headlines.js');

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:headliner/Resources/Private/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'sections' => $sections,
            // only with several languages the sections are labelled with their language
            'multiple' => count($sections) > 1,
            'columns' => count($sections),
            'collapsed' => $this->isCollapsed(),
            'tags' => HeaderTag::ALLOWED,
            'issueCount' => array_sum(array_column($sections, 'issueCount')),
            'anyLoaded' => in_array(true, array_column($sections, 'hasHeadings'), true),
        ]);
        $event->addHeaderContent($view->render('PageModule/Headlines'));
    }

    /**
     * @param list<\Twtgroup\Headliner\Structure\Heading> $headings
     * @param list<\Twtgroup\Headliner\Structure\Issue> $issues
     * @return list<array{tag: string, level: int, text: string, issues: list<string>, edit: string|null, editUid: int|null, canOpen: bool, canEditTag: bool, subheader: bool, fromText: bool, parentText: string}>
     */
    private function buildRows(array $headings, array $issues, int $languageId, EditAccess $editAccess): array
    {
        $rows = [];
        // The text of the header of every content element, so a subheader or a heading from the text can say whose it is
        $headerTexts = [];
        foreach ($headings as $heading) {
            $target = $heading->editTarget();
            if ($target !== null && $target['field'] === HeaderTag::FIELD) {
                $headerTexts[$target['uid']] = $heading->text;
            }
        }

        foreach ($headings as $index => $heading) {
            $target = $heading->editTarget();
            $isSubheader = $target !== null && $target['field'] === HeaderTag::SUBHEADER_FIELD;
            // A heading without an editable tag that is rendered in a content element, for example from its RTE text
            $fromText = $target === null && $heading->element !== null;
            $elementUid = $target['uid'] ?? $this->getTranslatedUid($heading->element, $languageId);
            $contentRecord = $elementUid !== null ? BackendUtility::getRecord('tt_content', $elementUid) : null;
            $canOpen = $contentRecord !== null && $editAccess->canOpen($contentRecord);
            $rows[] = [
                'tag' => $heading->level === 0 ? 'div' : 'h' . $heading->level,
                'level' => $heading->level,
                'text' => $heading->text,
                'issues' => array_values(array_map(
                    static fn($issue): string => $issue->type->value,
                    array_filter($issues, static fn($issue): bool => $issue->index === $index)
                )),
                'edit' => $heading->edit,
                // opens the content element: its own header, or the element the heading is rendered in
                'editUid' => $elementUid,
                'canOpen' => $canOpen,
                // the tag can be changed only if it belongs to this element and the user may change that field
                'canEditTag' => $canOpen && $target !== null && $heading->edit !== null && $contentRecord !== null && $editAccess->canEditTag($contentRecord, $target['field']),
                'subheader' => $isSubheader,
                'fromText' => $fromText,
                'parentText' => ($isSubheader || $fromText) && $elementUid !== null ? ($headerTexts[$elementUid] ?? '') : '',
            ];
        }

        return $rows;
    }

    /**
     * Whether the box is collapsed, saved by the JavaScript in the settings of the backend user. Read here, so the
     * box is rendered closed right away instead of closing after the page has loaded.
     */
    private function isCollapsed(): bool
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $setting = $backendUser instanceof BackendUserAuthentication ? ($backendUser->uc['headliner']['collapsed'] ?? 0) : 0;

        return (int) $setting === 1;
    }

    /**
     * The frame of a content element carries the uid of the record of the default language. On a translated page the
     * element to open is its translation.
     */
    private function getTranslatedUid(?int $uid, int $languageId): ?int
    {
        if ($uid === null || $languageId === 0) {
            return $uid;
        }
        $translation = BackendUtility::getRecordLocalization('tt_content', $uid, $languageId);

        return is_array($translation) && isset($translation[0]['uid']) ? (int) $translation[0]['uid'] : $uid;
    }

    /**
     * One section of the box: the structure of the page in one language.
     *
     * @param array<string, mixed> $page The page record
     * @return array<string, mixed>|null NULL if the site has no such language
     */
    private function buildSection(Site $site, array $page, int $languageId, EditAccess $editAccess): ?array
    {
        try {
            $language = $site->getLanguageById($languageId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $pageId = (int) $page['uid'];
        $section = [
            'languageId' => $languageId,
            'title' => $language->getTitle(),
            'flag' => $language->getFlagIdentifier(),
            'loaded' => false,
            'untranslated' => false,
            'hidden' => (bool) ($page['hidden'] ?? false),
            'hasHeadings' => false,
            'headings' => [],
            'pageIssues' => [],
            'issueCount' => 0,
        ];

        // A page that is not translated into the language has no frontend there
        $translation = $languageId > 0 ? BackendUtility::getRecordLocalization('pages', $pageId, $languageId) : [];
        if ($languageId > 0 && (!is_array($translation) || $translation === [])) {
            $section['untranslated'] = true;

            return $section;
        }

        // The translation of a page can be hidden on its own
        if (is_array($translation) && ($translation[0]['hidden'] ?? false)) {
            $section['hidden'] = true;
        }

        $html = $this->fetcher->fetch($site->getRouter()->generateUri($pageId, ['_language' => $languageId]));
        if ($html === null) {
            return $section;
        }

        $headings = $this->extractor->extract($html);
        $issues = $this->validator->validate($headings);
        $section['loaded'] = true;
        $section['hasHeadings'] = $headings !== [];
        $section['headings'] = $this->buildRows($headings, $issues, $languageId, $editAccess);
        $section['pageIssues'] = array_values(array_filter($issues, static fn($issue) => $issue->index === null));
        $section['issueCount'] = count($issues);

        return $section;
    }

    /**
     * @return list<int>
     */
    private function getLanguageIds(ServerRequestInterface $request): array
    {
        $moduleData = $request->getAttribute('moduleData');
        if (!$moduleData instanceof ModuleData) {
            return [0];
        }

        return SelectedLanguages::forView(
            $moduleData->get('languages'),
            PageViewMode::tryFrom((int) $moduleData->get('viewMode')) === PageViewMode::LanguageComparisonView
        );
    }
}
