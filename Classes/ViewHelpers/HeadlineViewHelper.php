<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use TYPO3\CMS\Core\Domain\RecordInterface;

/**
 * A heading of a record object: the content element of the template (`{record}` in `fluid_styled_content`, `{data}` in a
 * Content Block), or a card of a collection. The ViewHelper reads the tag, the layout and the record for the page
 * module from it:
 *
 *   <headliner:headline record="{record}">{record -> f:render.text(field: 'header')}</headliner:headline>
 *   <headliner:headline record="{record}" subheader="1">{record -> f:render.text(field: 'subheader')}</headliner:headline>
 *   <headliner:headline record="{card}">{card -> f:render.text(field: 'title')}</headliner:headline>
 *
 * The tag is "tag" if it is given, else the field of the record (`header_tag`, `subheader_tag` or the one tag field of
 * the table, or "tagField"). A heading in a record of another table is "offset" levels (one by default) below the header
 * of "parent" (the variable "data" if there is no parent) when it has no tag of its own, a subheader below its own
 * header. The page module finds the field by the attribute data-headliner-edit, which is only rendered for it.
 *
 * For values (an array, a partial without a record) use `headliner:headline.values`.
 */
final class HeadlineViewHelper extends AbstractHeadlineViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('record', RecordInterface::class, 'The record object the heading belongs to: the tag, the layout and the record for the page module are taken from it', true);
        $this->registerArgument('subheader', 'bool', 'Render a subheader instead of the header: its tag is "tag", else the field subheader_tag, else "offset" levels below the header of the parent or of the record', false, false);
        $this->registerArgument('textField', 'string', 'The field of "record" with the text of the heading, output with f:render.text (so visual_editor can edit it), when there is no content between the tags. Without it the label field of the table (the title of a card, the header of a content element), or "subheader" with "subheader"');
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div. It wins over the record. With "subheader" it is the tag of the subheader');
        $this->registerArgument('tagField', 'string', 'The field of "record" that holds the tag; "header_tag", or "subheader_tag" with "subheader". Without it the field header_tag if the record has it, else the one field of its table that is a select of tags');
        $this->registerArgument('parent', RecordInterface::class . '|array', 'The record whose header is the reference, the record of the content element (a record object, or its array); without it the variable "data" for a record of another table than tt_content: a heading without a tag of its own is rendered "offset" levels below that header; for a subheader without a parent it is its own record');
        $this->registerArgument('offset', 'int', 'How many levels below the header of "parent" a heading without a tag of its own is (negative: above)', false, 1);
    }

    public function render(): string
    {
        return $this->renderRecord($this->arguments['record']);
    }
}
