<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Backend\TagField;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\ViewHelpers\Headline\ChildrenViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Renders the header (or, with "subheader", the subheader) of a content element in the tag the editor
 * chose, with a CSS class for its layout, for example:
 *
 *   <headliner:headline tag="{data.header_tag}" layout="{data.header_layout}" default="{settings.defaultHeaderType}">
 *       {header}
 *   </headliner:headline>
 *
 * With the record object of the element (or of a card, say) the ViewHelper reads the tag, the layout and the record
 * for the page module itself:
 *
 *   <headliner:headline record="{record}">{record -> f:render.text(field: 'header')}</headliner:headline>
 *   <headliner:headline record="{card}" tagField="tag">{card -> f:render.text(field: 'title')}</headliner:headline>
 *
 * With "parent" (the record of the content element) a heading that has no tag of its own is "offset" levels (one by
 * default) below the header of the parent instead of the default.
 *
 * The page module finds the record behind a header by the attribute data-headliner-edit, which is only rendered for it.
 * It is taken from "record" (the field "tagField", "header_tag" or with "subheader" "subheader_tag"). Without a record
 * it is taken from the variable "data" of the content element; "edit" names it explicitly. An argument that is given
 * always wins over what the record says.
 *
 * Only h1-h6 and div can come out, so the value can never inject markup.
 */
final class HeadlineViewHelper extends AbstractTagBasedViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div. With "subheader" it is the tag of the subheader');
        $this->registerArgument('layout', 'string', 'The layout of the header (header_layout, 1-6): adds the CSS class ce-headline-layout-N and, for content without a tag, decides the tag');
        $this->registerArgument('default', 'string', 'Used if there is no tag and no layout 1-6: a number from 1 to 6. Without it the setting defaultHeaderType of the content element, else h2');
        $this->registerArgument('subheader', 'bool', 'Render a subheader instead of the header: its tag is "tag", else the field subheader_tag of the record, else "offset" levels below the header of the parent or of the record', false, false);
        $this->registerArgument('edit', 'string', 'Where the tag can be edited, "table:uid:field"; only rendered for the page module. Without it the record is taken from the variable "data" of the content element');
        $this->registerArgument('record', RecordInterface::class, 'The record object the heading belongs to: the tag, the layout and the record for the page module are taken from it');
        $this->registerArgument('tagField', 'string', 'The field of "record" that holds the tag; "header_tag", or "subheader_tag" with "subheader". Without it the field header_tag if the record has it, else the one field of its table that is a select of tags');
        $this->registerArgument('parent', RecordInterface::class . '|array', 'The record whose header is the reference, the record of the content element (a record object, or its array); without it the variable "data" for a record of another table than tt_content: a heading without a tag of its own is rendered "offset" levels below that header; for a subheader without a parent it is its own record');
        $this->registerArgument('offset', 'int', 'How many levels below the header of "parent" a heading without a tag of its own is (negative: above)', false, 1);
    }

    public function render(): string
    {
        $record = $this->arguments['record'];
        $variables = $this->renderingContext?->getVariableProvider();
        // the content element, as the templates of fluid_styled_content and a Content Block have it
        $data = $variables?->get('data');
        $subheader = (bool) $this->arguments['subheader'];
        $tagField = (string) ($this->arguments['tagField'] ?? '') !== '' ? (string) $this->arguments['tagField'] : self::tagFieldOf($record, $subheader);
        $own = $this->arguments['tag'] ?? self::valueOf($record, $tagField);
        // a record of another table (a card) is counted from the header of the content element, if the template has it
        $parent = $this->arguments['parent'] ?? (!$subheader && $record instanceof RecordInterface && $record->getMainType() !== 'tt_content'
            && ($data instanceof RecordInterface || is_array($data)) ? $data : null);
        $default = $this->arguments['default'] ?? $variables?->getByPath('settings.defaultHeaderType');

        // The header the level is counted from: the parent, or for a subheader its own record. The subheader has the
        // layout of its header.
        $reference = $this->arguments['subheader'] && !$parent instanceof RecordInterface && !is_array($parent) ? $record : $parent;
        $layout = $this->arguments['layout'] ?? self::valueOf($this->arguments['subheader'] ? $reference : $record, 'header_layout');
        if ($this->arguments['subheader']) {
            // the tag of the subheader, else `offset` levels below the header it belongs to
            $headerTag = HeaderTag::forContent(self::valueOf($reference, HeaderTag::FIELD), $layout, $default);
            $tag = HeaderTag::resolve($own) ?? HeaderTag::shifted($headerTag, (int) $this->arguments['offset']);
        } elseif (HeaderTag::resolve($own) === null && ($parent instanceof RecordInterface || is_array($parent))) {
            // no tag of its own: below the header of the parent, not the default and not a layout of its own
            $headerTag = HeaderTag::forContent(self::valueOf($parent, HeaderTag::FIELD), self::valueOf($parent, 'header_layout'), $default);
            $tag = HeaderTag::shifted($headerTag, (int) $this->arguments['offset']);
        } else {
            $tag = HeaderTag::forContent($own, $layout, $default);
        }
        $this->tag->setTagName($tag);

        $classes = array_filter([
            trim((string) $this->tag->getAttribute('class')),
            HeaderTag::layoutClass($layout),
        ]);
        $this->tag->addAttribute('class', implode(' ', $classes));
        if ($this->isInspectRequest()) {
            if ($record !== null) {
                // the record names its own field (none if it has no such field): never the header of the element
                $defaultEdit = EditMark::forRecord($record, $tagField);
            } else {
                // Inside of the content of a record (a card) the heading is not the header of the element: the page
                // module finds the field from the record. Only an explicit edit names it.
                $inRecord = $this->renderingContext?->getVariableProvider()->exists(ChildrenViewHelper::VARIABLE) === true;
                $defaultEdit = $inRecord ? null : EditMark::forContent($data, $this->arguments['subheader'] ? HeaderTag::SUBHEADER_FIELD : HeaderTag::FIELD);
            }
            $edit = $this->arguments['edit'] ?? $defaultEdit;
            if ($edit) {
                $this->tag->addAttribute('data-headliner-edit', $edit);
            }
            // a heading names its element itself: a Content Block has no frame with an id around its element
            $element = EditMark::elementUid($data) ?? EditMark::elementUid($record);
            if ($element !== null) {
                $this->tag->addAttribute('data-headliner-element', (string) $element);
            }
        }
        $this->tag->ignoreEmptyAttributes(true);
        $this->tag->setContent((string) $this->renderChildren());

        return $this->tag->render();
    }

    /**
     * The value of a field of a record object or of the array of a record, NULL if there is none or the field is not in it.
     */
    private static function valueOf(mixed $source, string $field): mixed
    {
        return match (true) {
            $source instanceof RecordInterface => $source->has($field) ? $source->get($field) : null,
            is_array($source) => $source[$field] ?? null,
            default => null,
        };
    }

    /**
     * The field of the record that holds the tag of the heading: `header_tag` (`subheader_tag`) if the record has it, else
     * the one field of its table that is a select of tags. If there are several the template has to say which with
     * `tagField`; the default name is then taken, which gives no mark if the record has no such field.
     */
    private static function tagFieldOf(mixed $record, bool $subheader): string
    {
        $standard = $subheader ? HeaderTag::SUBHEADER_FIELD : HeaderTag::FIELD;
        if (!$record instanceof RecordInterface || $subheader || $record->has($standard)) {
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

    private function isInspectRequest(): bool
    {
        $context = $this->renderingContext;
        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)) {
            return false;
        }

        return $context->getAttribute(ServerRequestInterface::class)->getAttribute(InspectRequest::ATTRIBUTE) === true;
    }
}
