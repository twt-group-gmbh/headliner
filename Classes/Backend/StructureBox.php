<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\FieldTranslationBehaviour;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\HeadingExtractor;
use Twtgroup\Headliner\Structure\HeadingValidator;
use Twtgroup\Headliner\Structure\PageHtmlFetcher;
use Twtgroup\Headliner\Structure\RteHeadings;
use Twtgroup\Headliner\Structure\Simulation;

/**
 * The box with the heading structure of a page: checks the page in every language and draws the result. The page
 * module only holds a placeholder and loads this box afterwards (see `StructureController`), because rendering the
 * frontend takes time and must not delay the page module.
 */
final readonly class StructureBox
{
    /** Name of the AJAX route that loads the box (key `headliner_structure` in `Configuration/Backend/AjaxRoutes.php`) */
    public const ROUTE = 'ajax_headliner_structure';

    /** Name of the AJAX route that changes the tag of a heading in a rich text field (key `headliner_rte_tag`) */
    public const RTE_ROUTE = 'ajax_headliner_rte_tag';

    /** Standard pages; folders, links, spacers etc. render no content. */
    private const DOKTYPE_STANDARD = 1;

    public function __construct(
        private SiteFinder $siteFinder,
        private PageHtmlFetcher $fetcher,
        private HeadingExtractor $extractor,
        private HeadingValidator $validator,
        private ViewFactoryInterface $viewFactory,
        private LocalizationRepository $localizationRepository,
        private TcaSchemaFactory $tcaSchemaFactory,
        private UriBuilder $uriBuilder,
        private RteHeadingEditor $rteEditor,
    ) {}

    /**
     * Whether the page has a box at all: a standard page of a site.
     */
    public function supports(int $pageId): bool
    {
        $page = $pageId > 0 ? BackendUtility::getRecord('pages', $pageId, 'doktype') : null;
        if ($page === null || (int) $page['doktype'] !== self::DOKTYPE_STANDARD) {
            return false;
        }
        try {
            $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return false;
        }

        return true;
    }

    /**
     * @param list<int> $languageIds The languages the page module shows
     * @param string $returnUrl Where the edit forms of the elements return to: the page module
     * @return string The HTML of the box, empty if there is nothing to show
     */
    public function render(ServerRequestInterface $request, int $pageId, array $languageIds, string $returnUrl): string
    {
        $page = $this->supports($pageId) ? BackendUtility::getRecord('pages', $pageId) : null;
        if ($page === null) {
            return '';
        }
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $backendUser = $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
        $editAccess = new EditAccess($backendUser, $page);
        // The workspace the backend user works in (0 is live): the page module shows its versions, so the structure does too
        $workspaceId = max(0, (int) ($backendUser->workspace ?? 0));
        if ($workspaceId > 0) {
            $page = BackendUtility::getRecordWSOL('pages', $pageId) ?? $page;
        }
        $site = $this->siteFinder->getSiteByPageId($pageId);

        $sections = [];
        foreach ($languageIds as $languageId) {
            $section = $this->buildSection($site, $page, $languageId, $editAccess, $workspaceId, $this->isEditRteHeadings($site));
            if ($section !== null) {
                $sections[] = $section;
            }
        }
        if ($sections === []) {
            return '';
        }

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:headliner/Resources/Private/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'sections' => $sections,
            // only with several languages the sections are labelled with their language
            'multiple' => count($sections) > 1,
            'columns' => count($sections),
            'workspace' => $workspaceId > 0 ? $this->getWorkspaceTitle($backendUser, $workspaceId) : '',
            'collapsed' => $this->isCollapsed(),
            'rteUrl' => (string) $this->uriBuilder->buildUriFromRoute(self::RTE_ROUTE),
            'issueCount' => array_sum(array_column($sections, 'issueCount')),
            'anyLoaded' => in_array(true, array_column($sections, 'hasHeadings'), true),
            'returnUrl' => $returnUrl,
        ]);

        return $view->render('PageModule/Headlines');
    }

    /**
     * @param list<\Twtgroup\Headliner\Structure\Heading> $headings
     * @param list<\Twtgroup\Headliner\Structure\Issue> $issues
     * @return list<array{tag: string, level: int, text: string, issues: list<string>, edit: string|null, rte: string|null, tagOptions: list<string>, editUid: int|null, canOpen: bool, canEditTag: bool, tagInherited: bool, outside: bool, subheader: bool, fromText: bool, parentText: string}>
     */
    private function buildRows(array $headings, array $issues, int $languageId, EditAccess $editAccess, bool $editRteHeadings): array
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

        // The place of a heading from the text among the headings of its content element, in the order of the text
        $textOrdinals = [];
        // What the editor of the text offers, per content element
        $textTags = [];

        foreach ($headings as $index => $heading) {
            $target = $heading->editTarget();
            $isSubheader = $target !== null && $target['field'] === HeaderTag::SUBHEADER_FIELD;
            // A heading without an editable tag that is rendered in a content element, for example from its RTE text
            $fromText = $target === null && $heading->element !== null;
            $elementUid = $target['uid'] ?? $this->getTranslatedUid($heading->element, $languageId);
            // In a workspace the version: it has the text the frontend rendered
            $contentRecord = $elementUid !== null ? BackendUtility::getRecordWSOL('tt_content', $elementUid) : null;
            $canOpen = $contentRecord !== null && $editAccess->canOpen($contentRecord);
            // l10n_mode "exclude" on the field: a translation has the value of its original, it cannot be changed there
            $tagInherited = $contentRecord !== null && $target !== null && $this->tagComesFromOriginal($contentRecord, $target['field']);
            $textOrdinal = null;
            if ($fromText) {
                $textOrdinal = $textOrdinals[$heading->element] = ($textOrdinals[$heading->element] ?? -1) + 1;
            }
            // A heading from the text is changed in the text itself, if it is where the order of the text says it is
            $rte = $editRteHeadings && $contentRecord !== null && $textOrdinal !== null && $canOpen && !$heading->outside
                && $this->rteEditor->canEditText($editAccess, $contentRecord)
                && $this->isHeadingInText($contentRecord, $textOrdinal, $heading);
            $rows[] = [
                'tag' => $heading->level === 0 ? 'div' : 'h' . $heading->level,
                'level' => $heading->level,
                'text' => $heading->text,
                'issues' => array_values(array_map(
                    static fn($issue): string => $issue->type->value,
                    array_filter($issues, static fn($issue): bool => $issue->index === $index)
                )),
                'edit' => $heading->edit,
                'rte' => $rte ? $elementUid . ':' . $textOrdinal : null,
                // a heading in a text offers what the editor of the text offers (and its own tag, so the select shows it)
                'tagOptions' => $rte ? $this->textTagOptions($textTags[$elementUid] ??= $this->rteEditor->allowedTags($contentRecord), $heading->level) : HeaderTag::ALLOWED,
                // opens the content element: its own header, or the element the heading is rendered in
                'editUid' => $elementUid,
                'canOpen' => $canOpen,
                // the tag can be changed only if it belongs to this element and the user may change that field
                'outside' => $heading->outside,
                'canEditTag' => $rte || ($canOpen && !$tagInherited && !$heading->outside && $target !== null && $heading->edit !== null && $contentRecord !== null && $editAccess->canEditTag($contentRecord, $target['field'])),
                'tagInherited' => $tagInherited,
                'subheader' => $isSubheader,
                'fromText' => $fromText,
                'parentText' => ($isSubheader || $fromText) && $elementUid !== null ? ($headerTexts[$elementUid] ?? '') : '',
            ];
        }

        return $rows;
    }

    private function isEditRteHeadings(Site $site): bool
    {
        return (bool) $site->getSettings()->get('headliner.editRteHeadings', false);
    }

    /**
     * @param list<string> $offered What the editor of the text offers
     * @return list<string>
     */
    private function textTagOptions(array $offered, int $currentLevel): array
    {
        $tags = array_values(array_unique([...$offered, 'h' . $currentLevel]));
        sort($tags);

        return $tags;
    }

    /**
     * Whether the heading is the one at that place in the rich text of the record: it has the same level and text, so
     * what the frontend rendered is what the field says (a text that is changed by the output, or a heading that
     * another source adds, does not match and is left alone).
     *
     * @param array<string, mixed> $contentRecord
     */
    private function isHeadingInText(array $contentRecord, int $ordinal, Heading $heading): bool
    {
        $inText = RteHeadings::all((string) ($contentRecord[RteHeadingEditor::FIELD] ?? ''))[$ordinal] ?? null;

        return $inText !== null && $inText['level'] === $heading->level && $inText['text'] === $heading->text;
    }

    /**
     * @param array<string, mixed> $contentRecord
     */
    private function tagComesFromOriginal(array $contentRecord, string $field): bool
    {
        if (!$this->tcaSchemaFactory->has('tt_content')) {
            return false;
        }
        $schema = $this->tcaSchemaFactory->get('tt_content');
        if (!$schema->isLanguageAware() || !$schema->hasField($field)) {
            return false;
        }
        $pointer = $schema->getCapability(TcaSchemaCapability::Language)->getTranslationOriginPointerField()->getName();

        return TranslationSync::inheritsFromOriginal(
            $schema->getField($field)->getTranslationBehaviour() === FieldTranslationBehaviour::Excluded,
            $contentRecord,
            $pointer
        );
    }

    private function getWorkspaceTitle(?BackendUserAuthentication $backendUser, int $workspaceId): string
    {
        $title = $backendUser !== null ? (string) ($backendUser->workspaceRec['title'] ?? '') : '';

        return $title !== '' ? $title : '#' . $workspaceId;
    }

    private function getGroupTitle(int $groupId): string
    {
        $group = BackendUtility::getRecord('fe_groups', $groupId, 'title');

        return is_array($group) && (string) $group['title'] !== '' ? (string) $group['title'] : '#' . $groupId;
    }

    /**
     * Whether the box is collapsed, saved by the JavaScript in the settings of the backend user. Read here, so the
     * box is rendered closed right away instead of closing after the page has loaded.
     */
    public function isCollapsed(): bool
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
        $translation = $this->localizationRepository->getRecordTranslation('tt_content', $uid, $languageId);

        return $translation?->getUid() ?? $uid;
    }

    /**
     * One section of the box: the structure of the page in one language.
     *
     * @param array<string, mixed> $page The page record (its version, in a workspace)
     * @param int $workspaceId The workspace of the backend user, 0 for live
     * @return array<string, mixed>|null NULL if the site has no such language
     */
    private function buildSection(Site $site, array $page, int $languageId, EditAccess $editAccess, int $workspaceId, bool $editRteHeadings): ?array
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
            'publication' => PublicationState::Published->value,
            'publicationFrom' => '',
            'publicationUntil' => '',
            'access' => AccessState::Open->value,
            'accessGroups' => '',
            'hasHeadings' => false,
            'headings' => [],
            'pageIssues' => [],
            'issueCount' => 0,
        ];

        // A page that is not translated into the language has no frontend there. In a workspace the translation is
        // its version: it can be hidden, have times or groups of its own
        $translationRecord = $languageId > 0
            ? $this->localizationRepository->getRecordTranslation('pages', $pageId, $languageId, $workspaceId)?->toArray()
            : null;
        if ($languageId > 0 && $translationRecord === null) {
            $section['untranslated'] = true;

            return $section;
        }

        // The translation of a page can be hidden on its own
        if ($translationRecord !== null && ($translationRecord['hidden'] ?? false)) {
            $section['hidden'] = true;
        }

        // A page with a start or end time is rendered at a time when it is published
        $rootLine = array_values(BackendUtility::BEgetRootLine($pageId, '', $workspaceId > 0));
        $publication = PublicationTime::forRootLine(
            $rootLine,
            $pageId,
            (int) $GLOBALS['EXEC_TIME'],
            $translationRecord
        );
        $section['publication'] = $publication->state->value;
        $locale = ($GLOBALS['LANG'] ?? null)?->getLocale();
        $section['publicationFrom'] = $publication->start > 0 ? LocalizedDate::format($publication->start, $locale) : '';
        $section['publicationUntil'] = $publication->end > 0 ? LocalizedDate::format($publication->end, $locale) : '';
        if ($publication->state === PublicationState::Never) {
            return $section;
        }

        // A page for frontend user groups is rendered as a member of them
        $access = FrontendGroupAccess::forRootLine($rootLine, $pageId, $translationRecord);
        $section['access'] = $access->state->value;
        $section['accessGroups'] = implode(', ', array_map($this->getGroupTitle(...), $access->groupIds));
        if ($access->state === AccessState::Never) {
            return $section;
        }

        $html = $this->fetcher->fetch(
            $site->getRouter()->generateUri($pageId, ['_language' => $languageId]),
            new Simulation($publication->simulatedTime, $access->simulatedGroups, $workspaceId > 0 ? $workspaceId : null)
        );
        if ($html === null) {
            return $section;
        }

        $headings = $this->extractor->extract($html, (string) $site->getSettings()->get('headliner.contentSelector', ''));
        $issues = $this->validator->validate($headings);
        $section['loaded'] = true;
        $section['hasHeadings'] = $headings !== [];
        $section['headings'] = $this->buildRows($headings, $issues, $languageId, $editAccess, $editRteHeadings);
        $section['pageIssues'] = array_values(array_filter($issues, static fn($issue) => $issue->index === null));
        $section['issueCount'] = count($issues);

        return $section;
    }
}
