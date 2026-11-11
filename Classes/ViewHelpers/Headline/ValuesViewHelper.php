<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers\Headline;

use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Frontend\RowTable;
use Twtgroup\Headliner\ViewHelpers\AbstractHeadlineViewHelper;

/**
 * A heading from values: the content element as an array, a partial that gets no record, a fixed heading.
 *
 *   <headliner:headline.values tag="{data.header_tag}" layout="{data.header_layout}">{header}</headliner:headline.values>
 *   <headliner:headline.values subheader="1" tag="{data.subheader_tag}" parent="{data}">{subheader}</headliner:headline.values>
 *
 * It sets the tag and the class from what it is given. For the page module it marks nothing on its own, the variable
 * "data" is never looked at: "edit" names where the tag is edited ("table:uid:field"). With a record object use
 * `headliner:headline`, which finds all of this itself.
 */
final class ValuesViewHelper extends AbstractHeadlineViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('row', 'array', 'Instead of values: a row as an array, as a data processor gives it (with all the columns of its table). A record is made of it, and the heading is the one of `headliner:headline`: the text from the label field, the tag from the tag field, the mark for the page module from the table, the uid and the field. "tag", "subheader", "parent", "offset", "textField" and "tagField" apply, "layout", "default" and "edit" do not');
        $this->registerArgument('table', 'string', 'The table of "row", if the content element does not link it with an inline field', false, '');
        $this->registerArgument('textField', 'string', 'With "row": the field with the text of the heading, if it is not the label field of the table');
        $this->registerArgument('tagField', 'string', 'With "row": the field with the tag, if the table has several that are a select of tags');
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div. With "subheader" it is the tag of the subheader');
        $this->registerArgument('layout', 'string', 'The layout of the header (header_layout, 1-6): adds the CSS class ce-headline-layout-N and, for content without a tag, decides the tag');
        $this->registerArgument('default', 'string', 'Used if there is no tag and no layout 1-6: a number from 1 to 6. Without it the setting defaultHeaderType of the content element, else h2');
        $this->registerArgument('subheader', 'bool', 'A switch, subheader="1": the heading is the subheader of an element, not its header. Its tag is "tag", else "offset" levels below the header of "parent", which is then needed', false, false);
        $this->registerArgument('edit', 'string', 'Where the tag can be edited, "table:uid:field"; only rendered for the page module');
        $this->registerArgument('parent', RecordInterface::class . '|array', 'The content element, a record object or its array, whose header is the reference: a subheader, or a heading without a tag, is "offset" levels below it');
        $this->registerArgument('offset', 'int', 'How many levels below the header of "parent" (negative: above)', false, 1);
    }

    public function render(): string
    {
        if (is_array($this->arguments['row'])) {
            $record = $this->recordOf($this->arguments['row']);

            // a row whose table is not known is no heading: there is nothing to read the text and the tag from
            return $record === null ? '' : $this->renderRecord($record);
        }
        $parent = $this->arguments['parent'];
        $default = $this->arguments['default'] ?? $this->defaultTag();
        $own = HeaderTag::resolve($this->arguments['tag']);
        // a subheader looks like its header
        $layout = $this->arguments['layout'] ?? ($this->arguments['subheader'] ? self::valueOf($parent, 'header_layout') : null);

        if ($own === null && $this->arguments['subheader'] && self::isRecord($parent)) {
            $tag = self::below($parent, (int) $this->arguments['offset'], $default);
        } elseif ($own === null && $this->arguments['subheader']) {
            // nothing to count from: one level below the default
            $tag = HeaderTag::shifted(HeaderTag::forContent(null, $layout, $default), (int) $this->arguments['offset']);
        } elseif ($own === null && self::isRecord($parent)) {
            $tag = self::below($parent, (int) $this->arguments['offset'], $default);
        } else {
            $tag = HeaderTag::forContent($own, $layout, $default);
        }

        return $this->output($tag, $layout, $this->arguments['edit'], EditMark::elementUid($this->contentElement()));
    }

    /**
     * The record of a row: its table is the one given, else the one the content element links the row from (the inline
     * field with that table as its `foreign_table`). Null if neither says. A row without all the columns of its table is
     * refused by the core, which names the missing one.
     *
     * @param array<mixed> $row
     */
    private function recordOf(array $row): ?RecordInterface
    {
        $table = (string) $this->arguments['table'];
        if ($table === '') {
            $data = $this->contentElement();
            $table = is_array($data) ? (RowTable::of($row, $data) ?? '') : '';
        }
        if ($table === '' || !GeneralUtility::makeInstance(TcaSchemaFactory::class)->has($table)) {
            return null;
        }

        return GeneralUtility::makeInstance(RecordFactory::class)->createResolvedRecordFromDatabaseRow($table, $row);
    }
}
