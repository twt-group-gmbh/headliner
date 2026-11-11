<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Schema\TagField;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Frontend\HeaderTag;

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
        $this->registerArgument('field', 'string', 'The field of "record" with the text of the heading, output with f:render.text (so visual_editor can edit it); without content between the tags. "header", or "subheader" with "subheader", if you do not name one');
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div. It wins over the record. With "subheader" it is the tag of the subheader');
        $this->registerArgument('tagField', 'string', 'The field of "record" that holds the tag; "header_tag", or "subheader_tag" with "subheader". Without it the field header_tag if the record has it, else the one field of its table that is a select of tags');
        $this->registerArgument('parent', RecordInterface::class . '|array', 'The record whose header is the reference, the record of the content element (a record object, or its array); without it the variable "data" for a record of another table than tt_content: a heading without a tag of its own is rendered "offset" levels below that header; for a subheader without a parent it is its own record');
        $this->registerArgument('offset', 'int', 'How many levels below the header of "parent" a heading without a tag of its own is (negative: above)', false, 1);
    }

    public function render(): string
    {
        $record = $this->arguments['record'];
        $data = $this->contentElement();
        $subheader = (bool) $this->arguments['subheader'];
        $tagField = (string) ($this->arguments['tagField'] ?? '') !== '' ? (string) $this->arguments['tagField'] : self::tagFieldOf($record, $subheader);
        $own = $this->arguments['tag'] ?? self::valueOf($record, $tagField);
        // a record of another table (a card) is counted from the header of the content element, if the template has it
        $parent = $this->arguments['parent'] ?? (!$subheader && $record->getMainType() !== 'tt_content' && self::isRecord($data) ? $data : null);
        $default = $this->defaultTag();
        // the header the level is counted from: the parent, or for a subheader its own record
        $reference = self::isRecord($parent) ? $parent : ($subheader ? $record : null);
        // a subheader has the layout of its header
        $layout = self::valueOf($subheader ? $reference : $record, 'header_layout');

        if ($subheader) {
            $tag = HeaderTag::resolve($own) ?? self::below($reference, (int) $this->arguments['offset'], $default);
        } elseif (HeaderTag::resolve($own) === null && $reference !== null) {
            // no tag of its own: below the header of the parent, not the default and not a layout of its own
            $tag = self::below($reference, (int) $this->arguments['offset'], $default);
        } else {
            $tag = HeaderTag::forContent($own, $layout, $default);
        }

        // the text: what is between the tags, else the field of the record
        $content = (string) $this->renderChildren();
        if (trim($content) === '') {
            $content = $this->textOf($record, (string) ($this->arguments['field'] ?? '') !== '' ? (string) $this->arguments['field'] : ($subheader ? 'subheader' : 'header'));
            if ($content === '') {
                // nothing to say: no empty heading
                return '';
            }
        }

        return $this->output($tag, $layout, EditMark::forRecord($record, $tagField), EditMark::elementUid($data) ?? EditMark::elementUid($record), $content);
    }

    /**
     * The text of a field of the record as `f:render.text` gives it. The ViewHelper is the one the namespace `f` has for
     * the template (`visual_editor` replaces it, to mark what an editor can change), so it is looked up and not called
     * by its class. Empty if the record has no such field.
     */
    private function textOf(RecordInterface $record, string $field): string
    {
        $context = $this->renderingContext;
        if ($context === null || !$record->has($field)) {
            return '';
        }
        $text = $context->getViewHelperInvoker()->invoke(
            $context->getViewHelperResolver()->createViewHelperInstance('f', 'render.text'),
            ['record' => $record, 'field' => $field],
            $context
        );

        return (string) $text;
    }

    /**
     * The field of the record that holds the tag of the heading: `header_tag` (`subheader_tag`) if the record has it, else
     * the one field of its table that is a select of tags. If there are several the template has to say which with
     * `tagField`; the default name is then taken, which gives no mark if the record has no such field.
     */
    private static function tagFieldOf(RecordInterface $record, bool $subheader): string
    {
        $standard = $subheader ? HeaderTag::SUBHEADER_FIELD : HeaderTag::FIELD;
        if ($subheader || $record->has($standard)) {
            return $standard;
        }
        $table = $record->getMainType();
        $schemaFactory = GeneralUtility::makeInstance(TcaSchemaFactory::class);
        if (!$schemaFactory->has($table)) {
            return $standard;
        }
        $tagFields = new TagField($schemaFactory);
        $candidates = [];
        foreach ($schemaFactory->get($table)->getFields() as $field) {
            if ($tagFields->is($table, $field->getName())) {
                $candidates[] = $field->getName();
            }
        }

        return count($candidates) === 1 ? $candidates[0] : $standard;
    }
}
