<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\FieldTranslationBehaviour;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Twtgroup\Headliner\Event\ModifyHeadingIssuesEvent;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\HeadingExtractor;
use Twtgroup\Headliner\Structure\HeadingValidator;
use Twtgroup\Headliner\Structure\Issue;
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

    /** The hues of the groups, one after the other: apart from each other, and not the colours of the levels */
    private const GROUP_HUES = [285, 215, 160, 95, 40, 350];

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
        private TagField $tagField,
        private EventDispatcherInterface $eventDispatcher,
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
        // The page TSconfig hides fields in the form (TCEFORM): a field the form does not show is not changed in the box
        $editAccess = new EditAccess($backendUser, $page, new HiddenFields(BackendUtility::getPagesTSconfig($pageId)));
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
     * @return list<array<string, mixed>> One row per heading: its tag, text, problems, what the user may do with it and
     *     the place in its group (see `markGroups()`)
     */
    private function buildRows(array $headings, array $issues, int $languageId, EditAccess $editAccess, bool $editRteHeadings, int $pageId): array
    {
        $rows = [];
        // The place of a heading from a text among the headings of that text, in the order of the text, per text
        $textOrdinals = [];
        // What the editor of the text offers, per text
        $textTags = [];
        // The headings of a marked record that were found in one of its rich texts already: "table:uid" => field => place
        $usedInRecord = [];

        foreach ($headings as $index => $heading) {
            $target = $heading->editTarget();
            // The mark is part of the rendered page, which an editor can write to: it counts only for a field that is
            // one for a tag, never for another field of another table
            if ($target !== null && !$this->tagField->is($target['table'], $target['field'])) {
                $target = null;
            }
            // A heading in a record the template marked (a card) has no mark of its own: the field is found from the
            // record, in its rich texts or in the field with its tag
            $textTarget = null;
            $recordTarget = $heading->recordTarget();
            if ($target === null && $recordTarget !== null) {
                ['tag' => $target, 'text' => $textTarget] = $this->resolveInRecord($recordTarget, $heading, $usedInRecord, $pageId);
            }
            $isElementTarget = $target !== null && $target['table'] === 'tt_content';
            $isSubheader = $isElementTarget && $target['field'] === HeaderTag::SUBHEADER_FIELD;
            // A heading without an editable tag that is rendered in a content element, for example from its RTE text
            // @extensionScannerIgnoreLine the element uid of our Heading, not a property of the history classes
            $fromText = $target === null && ($heading->element !== null || $textTarget !== null);
            // The content element: the record of the mark, or the one the heading says it is rendered in (the record
            // of a collection, a card of a Content Block, is not the element)
            // @extensionScannerIgnoreLine the element uid of our Heading, not a property of the history classes
            $elementUid = $isElementTarget ? $target['uid'] : $this->getTranslatedUid($heading->element, $languageId);
            // In a workspace the version: it has the text the frontend rendered
            $contentRecord = $elementUid !== null ? BackendUtility::getRecordWSOL('tt_content', $elementUid) : null;
            $canOpen = $contentRecord !== null && $editAccess->canOpen($contentRecord);
            // l10n_mode "exclude" on the field: a translation has the value of its original, it cannot be changed there
            // The record behind the tag of a heading that is not the element: a card of a collection
            $childRecord = $target !== null && !$isElementTarget ? BackendUtility::getRecordWSOL($target['table'], $target['uid']) : null;
            // A record is only changed from the box of its own page: the marks are part of the page, which an editor writes, so
            // a mark for a record of another page must not make this box change it
            $tagRecord = $isElementTarget ? $contentRecord : $childRecord;
            $tagRecord = $tagRecord !== null && $this->isOnPage($tagRecord, $pageId) ? $tagRecord : null;
            $tagInherited = $tagRecord !== null && $target !== null && $this->tagComesFromOriginal($target['table'], $tagRecord, $target['field']);
            // The rich text a heading from a text is in: the text a template marked (the text of a card, say), or the
            // text of the content element the frame names
            $textSource = $textTarget !== null
                ? ['table' => $textTarget['table'], 'uid' => $textTarget['uid'], 'field' => $textTarget['field']]
                : (!$fromText ? null : ($elementUid !== null
                    ? ['table' => 'tt_content', 'uid' => $elementUid, 'field' => RteHeadingEditor::FIELD]
                    : null));
            $textKey = $textSource !== null ? $textSource['table'] . ':' . $textSource['uid'] . ':' . $textSource['field'] : null;
            $textOrdinal = null;
            if ($textTarget !== null) {
                // found in the text already, at its place
                $textOrdinal = $textTarget['ordinal'];
            } elseif ($textKey !== null) {
                $textOrdinal = $textOrdinals[$textKey] = ($textOrdinals[$textKey] ?? -1) + 1;
            }
            $textRecord = $textSource === null ? null : (
                $textSource['table'] === 'tt_content' && $textSource['uid'] === $elementUid
                    ? $contentRecord
                    : BackendUtility::getRecordWSOL($textSource['table'], $textSource['uid'])
            );
            // A heading from a text is changed in the text itself, if it is where the order of the text says it is
            $rte = $editRteHeadings && $textSource !== null && $textRecord !== null && $this->isOnPage($textRecord, $pageId) && $textOrdinal !== null && !$heading->outside
                && $this->rteEditor->canEditText($editAccess, $textSource['table'], $textRecord, $textSource['field'])
                && $this->isHeadingInText($textRecord, $textSource['field'], $textOrdinal, $heading);
            $rows[] = [
                'tag' => $heading->level === 0 ? 'div' : 'h' . $heading->level,
                'level' => $heading->level,
                'text' => $heading->text,
                'issues' => array_values(array_map(
                    fn(Issue $issue): array => $this->issueRow($issue),
                    array_filter($issues, static fn(Issue $issue): bool => $issue->index === $index)
                )),
                'edit' => $target !== null ? $target['table'] . ':' . $target['uid'] . ':' . $target['field'] : null,
                'childLabel' => $target !== null && !$isElementTarget ? $this->tableTitle($target['table']) : '',
                'group' => $elementUid,
                'groupPosition' => '',
                'groupHue' => 0,
                'rte' => $rte ? $textKey . ':' . $textOrdinal : null,
                // a heading in a text offers what the editor of the text offers (and its own tag, so the select shows it)
                'tagOptions' => $rte
                    ? $this->textTagOptions($textTags[$textKey] ??= $this->rteEditor->allowedTags($textSource['table'], $textSource['field'], $textRecord), $heading->level)
                    : ($target !== null ? $this->tagField->options($target['table'], $target['field']) : HeaderTag::ALLOWED),
                // opens the content element: its own header, or the element the heading is rendered in
                'editUid' => $elementUid,
                'canOpen' => $canOpen,
                // the tag can be changed only if it belongs to this element and the user may change that field
                'outside' => $heading->outside,
                'canEditTag' => $rte || (!$tagInherited && !$heading->outside && $target !== null && $tagRecord !== null && $this->mayEditTag($editAccess, $target, $tagRecord, $canOpen)),
                'tagInherited' => $tagInherited,
                'subheader' => $isSubheader,
                'fromText' => $fromText,
            ];
        }

        return $this->markGroups($rows);
    }

    /**
     * The headings of one content element (its header, its subheader, the headings of its text) follow each other in
     * the list. A run of two or more of them is a group: the rows get their place in it (start, middle, end) and a
     * colour (a hue, taken in turn per group), so the box can draw them as one.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function markGroups(array $rows): array
    {
        $hues = [];
        foreach ($rows as $index => $row) {
            $group = $row['group'];
            $joinsPrevious = $group !== null && ($rows[$index - 1]['group'] ?? null) === $group;
            $joinsNext = $group !== null && ($rows[$index + 1]['group'] ?? null) === $group;
            if (!$joinsPrevious && !$joinsNext) {
                continue;
            }
            $hues[$group] ??= self::GROUP_HUES[count($hues) % count(self::GROUP_HUES)];
            $rows[$index]['groupPosition'] = !$joinsPrevious ? 'start' : (!$joinsNext ? 'end' : 'middle');
            $rows[$index]['groupHue'] = $hues[$group];
        }

        return $rows;
    }

    /**
     * Whether the record is on the page: its content elements, and the records of their collections, have the page as their pid
     * (in a workspace and in a translation as well).
     *
     * @param array<string, mixed> $record
     */
    private function isOnPage(array $record, int $pageId): bool
    {
        return (int) ($record['pid'] ?? 0) === $pageId;
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
     * @param array<string, mixed> $record The record with the text
     */
    private function isHeadingInText(array $record, string $field, int $ordinal, Heading $heading): bool
    {
        $inText = RteHeadings::all((string) ($record[$field] ?? ''))[$ordinal] ?? null;

        return $inText !== null && $inText['level'] === $heading->level && $inText['text'] === $heading->text;
    }

    /**
     * The field behind a heading in a record that a template marked (a card of a Content Block): the rich text the
     * heading is written in (the first heading of a text that has its level and text and is not taken yet), or else
     * the one field with its tag (a select of tags whose value is the tag the heading was rendered with). None if it
     * is neither, or the tag fields cannot be told apart.
     *
     * @param array{table: string, uid: int} $recordTarget
     * @param array<string, array<string, array<int, true>>> $used
     * @return array{tag: array{table: string, uid: int, field: string}|null, text: array{table: string, uid: int, field: string, ordinal: int}|null}
     */
    private function resolveInRecord(array $recordTarget, Heading $heading, array &$used, int $pageId): array
    {
        $none = ['tag' => null, 'text' => null];
        ['table' => $table, 'uid' => $uid] = $recordTarget;
        $record = $this->tcaSchemaFactory->has($table) ? BackendUtility::getRecordWSOL($table, $uid) : null;
        if ($record === null || !$this->isOnPage($record, $pageId)) {
            return $none;
        }
        $fields = array_map(static fn($field): string => $field->getName(), iterator_to_array($this->tcaSchemaFactory->get($table)->getFields(), false));

        $key = $table . ':' . $uid;
        foreach ($fields as $field) {
            if (!$this->rteEditor->isRichTextField($table, $field, $record)) {
                continue;
            }
            foreach (RteHeadings::all((string) ($record[$field] ?? '')) as $ordinal => $inText) {
                if (!isset($used[$key][$field][$ordinal]) && $inText['level'] === $heading->level && $inText['text'] === $heading->text) {
                    $used[$key][$field][$ordinal] = true;

                    return ['tag' => null, 'text' => ['table' => $table, 'uid' => $uid, 'field' => $field, 'ordinal' => $ordinal]];
                }
            }
        }

        $rendered = $heading->level === 0 ? 'div' : 'h' . $heading->level;
        $matches = array_values(array_filter(
            $fields,
            fn(string $field): bool => $this->tagField->is($table, $field) && ($record[$field] ?? null) === $rendered
        ));

        return count($matches) === 1 ? ['tag' => ['table' => $table, 'uid' => $uid, 'field' => $matches[0]], 'text' => null] : $none;
    }

    /**
     * Whether the backend user may change the tag field of the record behind a heading: of the content element (the
     * rules of the page module for its buttons), or of the record of a collection, by the rights for that table.
     *
     * @param array{table: string, uid: int, field: string} $target
     * @param array<string, mixed> $tagRecord
     */
    private function mayEditTag(EditAccess $editAccess, array $target, array $tagRecord, bool $canOpenElement): bool
    {
        if ($target['table'] === 'tt_content') {
            return $canOpenElement && $editAccess->canEditTag($tagRecord, $target['field']);
        }
        $schema = $this->tcaSchemaFactory->get($target['table']);

        return $editAccess->canEditFieldOf($target['table'], $tagRecord, $target['field'], $schema->getField($target['field'])->supportsAccessControl());
    }

    /**
     * The name of a table for the badge of a heading from a collection (the title of its TCA).
     */
    private function tableTitle(string $table): string
    {
        $title = $this->tcaSchemaFactory->has($table) ? $this->tcaSchemaFactory->get($table)->getTitle(
            static fn(string $title): string => (($GLOBALS['LANG'] ?? null)?->sL($title) ?? '') ?: $title
        ) : '';

        return $title !== '' ? $title : $table;
    }

    /**
     * @param array<string, mixed> $record A record of that table
     */
    private function tagComesFromOriginal(string $table, array $record, string $field): bool
    {
        if (!$this->tcaSchemaFactory->has($table)) {
            return false;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->isLanguageAware() || !$schema->hasField($field)) {
            return false;
        }
        $pointer = $schema->getCapability(TcaSchemaCapability::Language)->getTranslationOriginPointerField()->getName();

        return TranslationSync::inheritsFromOriginal(
            $schema->getField($field)->getTranslationBehaviour() === FieldTranslationBehaviour::Excluded,
            $record,
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
        $issues = $this->checkedByListeners($headings, $this->validator->validate($headings), $pageId, $languageId);
        $section['loaded'] = true;
        $section['hasHeadings'] = $headings !== [];
        $section['headings'] = $this->buildRows($headings, $issues, $languageId, $editAccess, $editRteHeadings, $pageId);
        $section['pageIssues'] = array_values(array_map(
            fn(Issue $issue): array => $this->issueRow($issue),
            array_filter($issues, static fn(Issue $issue): bool => $issue->index === null)
        ));
        $section['issueCount'] = count($issues);

        return $section;
    }

    /**
     * The rules of a project: listeners add issues or take the ones of the built-in rules away. What they give back is
     * checked here, not trusted: an issue needs a name and, if it names a heading, a heading that is there.
     *
     * @param list<\Twtgroup\Headliner\Structure\Heading> $headings
     * @param list<Issue> $issues
     * @return list<Issue> The page's issues first, then by heading
     */
    private function checkedByListeners(array $headings, array $issues, int $pageId, int $languageId): array
    {
        // the dispatcher hands the same object back, with what the listeners did to it
        $event = new ModifyHeadingIssuesEvent($headings, $issues, $pageId, $languageId);
        $this->eventDispatcher->dispatch($event);
        $valid = array_values(array_filter(
            $event->getIssues(),
            static fn(mixed $issue): bool => $issue instanceof Issue && self::isUsable($issue, count($headings))
        ));
        // the sort is stable: the issues of one place stay in the order they were found in
        usort($valid, static fn(Issue $a, Issue $b): int => ($a->index ?? -1) <=> ($b->index ?? -1));

        return $valid;
    }

    /**
     * Whether an issue of a listener can be shown: it has a name and, if it names a heading, a heading that is there.
     */
    private static function isUsable(Issue $issue, int $headingCount): bool
    {
        // @extensionScannerIgnoreLine the name of our issue (a string), not the type property of the TypoScriptFrontendController
        $named = $issue->type !== '';

        return $named && ($issue->index === null || ($issue->index >= 0 && $issue->index < $headingCount));
    }

    /**
     * What the template needs of an issue: its name and, for a rule of a listener, the text. The built-in rules have none
     * here, the template takes their text from the language file of the extension.
     *
     * @return array{type: string, label: string}
     */
    private function issueRow(Issue $issue): array
    {
        if ($issue->label === null) {
            // @extensionScannerIgnoreLine the name of our issue (a string), not the type property of the TypoScriptFrontendController
            return ['type' => $issue->type, 'label' => ''];
        }
        $language = $GLOBALS['LANG'] ?? null;
        $label = $language instanceof LanguageService ? $language->sL($issue->label) : $issue->label;

        // an `LLL:` reference that does not resolve shows the name rather than nothing
        // @extensionScannerIgnoreLine the name of our issue (a string), not the type property of the TypoScriptFrontendController
        return ['type' => $issue->type, 'label' => $label !== '' ? $label : $issue->type];
    }
}
