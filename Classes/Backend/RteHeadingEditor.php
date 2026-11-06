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
    /** The rich text of a content element, where a heading in it that has no mark of its own is searched */
    public const FIELD = 'bodytext';

    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private Richtext $richtext,
        private SiteFinder $siteFinder,
    ) {}

    /**
     * Whether the field of this record is a rich text: a text field with the rich text editor on (for a content
     * element, that is set per type of element). The mark with the field is part of the rendered page, which an editor
     * can write to, so only such a field is ever changed.
     *
     * @param array<string, mixed> $record The record with the field
     */
    public function isRichTextField(string $table, string $field, array $record): bool
    {
        if (!$this->tcaSchemaFactory->has($table)) {
            return false;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->hasField($field)) {
            return false;
        }
        $configuration = $schema->getField($field)->getConfiguration();
        // the type of the record can change the field (a content element: the rich text is on for some types only)
        if ($schema->supportsSubSchema()) {
            $type = (string) ($record[$schema->getSubSchemaTypeInformation()->getFieldName()] ?? '');
            if ($type !== '' && $schema->hasSubSchema($type) && $schema->getSubSchema($type)->hasField($field)) {
                $configuration = $schema->getSubSchema($type)->getField($field)->getConfiguration();
            }
        }

        return ($configuration['type'] ?? null) === 'text' && !empty($configuration['enableRichtext']);
    }

    /**
     * Whether the backend user may change the text: the rules of the page module for editing the record, and the
     * permission for the field if it is an "exclude" field.
     *
     * @param array<string, mixed> $record The record with the text
     */
    public function canEditText(EditAccess $access, string $table, array $record, string $field): bool
    {
        if (!$this->isRichTextField($table, $field, $record)) {
            return false;
        }
        $excludeField = $this->tcaSchemaFactory->get($table)->getField($field)->supportsAccessControl();

        return $access->canEditFieldOf($table, $record, $field, $excludeField);
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
     * The headings the editor of the text offers: `heading.options` of the configuration of the RTE (the preset, changed
     * by the page TSconfig), as for the editor itself.
     *
     * @param array<string, mixed> $record The record with the text
     * @return list<string> h1 to h6
     */
    public function allowedTags(string $table, string $field, array $record): array
    {
        $schema = $this->tcaSchemaFactory->has($table) ? $this->tcaSchemaFactory->get($table) : null;
        $fieldConfiguration = $schema !== null && $schema->hasField($field) ? $schema->getField($field)->getConfiguration() : [];
        $configuration = $this->richtext->getConfiguration(
            $table,
            $field,
            (int) ($record['pid'] ?? 0),
            (string) ($record['CType'] ?? ''),
            $fieldConfiguration
        );
        $options = $configuration['editor']['config']['heading']['options'] ?? null;

        return RteHeadings::tagsFromHeadingOptions(is_array($options) ? $options : null);
    }

    /**
     * @param string $table The table of the record with the text (a content element, or a record of a collection)
     * @param int $uid The record (its live uid, also in a workspace)
     * @param string $field The rich text field
     * @param int $ordinal Position of the heading among the headings of the text, starting at 0
     * @param string $tag h1 to h6
     * @param string $expectedText The text of the heading as the box showed it
     */
    public function change(string $table, int $uid, string $field, int $ordinal, string $tag, string $expectedText): RteChange
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication || preg_match('/^h([1-6])$/', $tag, $match) !== 1
            || !$this->tcaSchemaFactory->has($table)
        ) {
            return RteChange::NotFound;
        }
        // in a workspace the version: it has the text the frontend rendered
        $record = $uid > 0 ? BackendUtility::getRecordWSOL($table, $uid) : null;
        $page = is_array($record) ? BackendUtility::getRecord('pages', (int) $record['pid']) : null;
        // a field that is no rich text (a mark in the page can name any) is never changed
        if (!is_array($record) || $page === null || !$this->isRichTextField($table, $field, $record)) {
            return RteChange::NotFound;
        }
        if (!$this->isEnabledFor((int) $page['uid']) || !$this->canEditText(new EditAccess($backendUser, $page), $table, $record, $field)) {
            return RteChange::NotAllowed;
        }

        // only what the editor of the text offers
        if (!in_array($tag, $this->allowedTags($table, $field, $record), true)) {
            return RteChange::NotAllowed;
        }

        $html = (string) ($record[$field] ?? '');
        $heading = RteHeadings::all($html)[$ordinal] ?? null;
        $changed = $heading !== null && $heading['text'] === $expectedText ? RteHeadings::withLevel($html, $ordinal, (int) $match[1]) : null;
        if ($changed === null) {
            return RteChange::Mismatch;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([$table => [$uid => [$field => $changed]]], [], $backendUser);
        $dataHandler->process_datamap();

        return $dataHandler->errorLog === [] ? RteChange::Changed : RteChange::Failed;
    }
}
