<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers\Headline;

use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Frontend\RowTable;
use Twtgroup\Headliner\ViewHelpers\AbstractHeadlineViewHelper;

/**
 * A heading of a row as an array, as a data processor such as `database-query` gives it:
 *
 *   <f:for each="{items}" as="item">
 *       <headliner:headline.row row="{item.data}" />
 *   </f:for>
 *
 * A record is made of the row, and the heading is the one of `headliner:headline`: the text from the label field of the
 * table, the tag from its tag field, the mark for the page module from the table, the uid and the field. The table is found
 * from the inline field of the content element that links the row, or named with `table`.
 */
final class RowViewHelper extends AbstractHeadlineViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('row', 'array', 'The row as a data processor gives it, with all the columns of its table', true);
        $this->registerArgument('table', 'string', 'The table of "row", if the content element does not link it with an inline field', false, '');
        $this->registerArgument('subheader', 'bool', 'A switch, subheader="1": the heading is the subheader of the record, not its header. The text is the field subheader, the tag is "tag", else the field subheader_tag, else "offset" levels below the header', false, false);
        $this->registerArgument('textField', 'string', 'The field with the text of the heading, if it is not the label field of the table, when there is no content between the tags');
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div. It wins over the record. With "subheader" it is the tag of the subheader');
        $this->registerArgument('tagField', 'string', 'The field that holds the tag, if the table has several that are a select of tags');
        $this->registerArgument('default', 'string', 'Used if the record has no tag and no layout 1-6: a tag (h1-h6, div) or a layout number from 1 to 6. Without it the setting defaultHeaderType of the content element, else h2');
        $this->registerArgument('parent', RecordInterface::class . '|array', 'The content element, a record object or its array, whose header the heading is counted from; without it the variable "data"');
        $this->registerArgument('offset', 'int', 'How many levels below the header of "parent" a heading without a tag of its own is (negative: above)', false, 1);
    }

    public function render(): string
    {
        $record = $this->recordOf($this->arguments['row']);

        // a row whose table is not known is no heading: there is nothing to read the text and the tag from
        return $record === null ? '' : $this->renderRecord($record);
    }

    /**
     * The record of the row: its table is the one given, else the one the content element links the row from (the inline
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
