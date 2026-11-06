<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Structure\RteHeadings;

/**
 * Changes the tag of a heading in the rich text of a content element. The heading is found by its place among the
 * headings of the text and checked by its text, so a text that was changed since the box was drawn is never changed
 * at the wrong place. The change is saved through the DataHandler, with the rights of the backend user, history and
 * workspaces included.
 */
final readonly class RteHeadingEditor
{
    /** The field with the rich text */
    public const FIELD = 'bodytext';

    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private Richtext $richtext,
        private SiteFinder $siteFinder,
    ) {}

    /**
     * Whether the backend user may change the text of the element; the rules of the page module for editing it, and
     * the permission for the field if it is an "exclude" field.
     *
     * @param array<string, mixed> $contentRecord
     */
    public function canEditText(EditAccess $access, array $contentRecord): bool
    {
        $schema = $this->tcaSchemaFactory->get('tt_content');
        $excludeField = !$schema->hasField(self::FIELD) || $schema->getField(self::FIELD)->supportsAccessControl();

        return $access->canEditField($contentRecord, self::FIELD, $excludeField);
    }

    /**
     * The site setting `headliner.editRteHeadings`: off by default.
     */
    private function isEnabledFor(int $pageId): bool
    {
        try {
            return (bool) $this->siteFinder->getSiteByPageId($pageId)->getSettings()->get('headliner.editRteHeadings', false);
        } catch (SiteNotFoundException) {
            return false;
        }
    }

    /**
     * The headings the editor of the text offers for this element: `heading.options` of the configuration of the RTE
     * (the preset, changed by the page TSconfig), as for the editor itself.
     *
     * @param array<string, mixed> $contentRecord
     * @return list<string> h1 to h6
     */
    public function allowedTags(array $contentRecord): array
    {
        $schema = $this->tcaSchemaFactory->get('tt_content');
        $fieldConfiguration = $schema->hasField(self::FIELD) ? $schema->getField(self::FIELD)->getConfiguration() : [];
        $configuration = $this->richtext->getConfiguration(
            'tt_content',
            self::FIELD,
            (int) ($contentRecord['pid'] ?? 0),
            (string) ($contentRecord['CType'] ?? ''),
            $fieldConfiguration
        );
        $options = $configuration['editor']['config']['heading']['options'] ?? null;

        return RteHeadings::tagsFromHeadingOptions(is_array($options) ? $options : null);
    }

    /**
     * @param int $uid The content element (its live uid, also in a workspace)
     * @param int $ordinal Position of the heading among the headings of the text, starting at 0
     * @param string $tag h1 to h6
     * @param string $expectedText The text of the heading as the box showed it
     */
    public function change(int $uid, int $ordinal, string $tag, string $expectedText): RteChange
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication || preg_match('/^h([1-6])$/', $tag, $match) !== 1) {
            return RteChange::NotFound;
        }
        // in a workspace the version: it has the text the frontend rendered
        $record = $uid > 0 ? BackendUtility::getRecordWSOL('tt_content', $uid) : null;
        $page = is_array($record) ? BackendUtility::getRecord('pages', (int) $record['pid']) : null;
        if (!is_array($record) || $page === null) {
            return RteChange::NotFound;
        }
        if (!$this->isEnabledFor((int) $page['uid']) || !$this->canEditText(new EditAccess($backendUser, $page), $record)) {
            return RteChange::NotAllowed;
        }

        // only what the editor of the text offers
        if (!in_array($tag, $this->allowedTags($record), true)) {
            return RteChange::NotAllowed;
        }

        $html = (string) ($record[self::FIELD] ?? '');
        $heading = RteHeadings::all($html)[$ordinal] ?? null;
        $changed = $heading !== null && $heading['text'] === $expectedText ? RteHeadings::withLevel($html, $ordinal, (int) $match[1]) : null;
        if ($changed === null) {
            return RteChange::Mismatch;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['tt_content' => [$uid => [self::FIELD => $changed]]], [], $backendUser);
        $dataHandler->process_datamap();

        return $dataHandler->errorLog === [] ? RteChange::Changed : RteChange::Failed;
    }
}
