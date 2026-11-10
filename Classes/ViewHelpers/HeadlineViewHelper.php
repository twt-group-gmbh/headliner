<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
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
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div');
        $this->registerArgument('layout', 'string', 'The layout of the header (header_layout), only used for the CSS class');
        $this->registerArgument('default', 'string', 'Layout to take the tag from if no tag is set (older content)');
        $this->registerArgument('subheader', 'bool', 'Render a subheader instead of the header', false, false);
        $this->registerArgument('edit', 'string', 'Where the tag can be edited, "table:uid:field"; only rendered for the page module. Without it the record is taken from the variable "data" of the content element');
        $this->registerArgument('record', 'mixed', 'The record object the heading belongs to: the tag, the layout and the record for the page module are taken from it (anything that is no record object is ignored)');
        $this->registerArgument('tagField', 'string', 'The field of "record" that holds the tag; "header_tag", or "subheader_tag" with "subheader"');
        $this->registerArgument('subheaderTag', 'string', 'The tag chosen for the subheader; without it the subheader is one level below the header');
    }

    public function render(): string
    {
        $record = $this->arguments['record'] instanceof RecordInterface ? $this->arguments['record'] : null;
        $tagField = (string) ($this->arguments['tagField'] ?? '') !== ''
            ? (string) $this->arguments['tagField']
            : ($this->arguments['subheader'] ? HeaderTag::SUBHEADER_FIELD : HeaderTag::FIELD);
        $layout = $this->arguments['layout'] ?? self::fieldOf($record, 'header_layout');

        if ($this->arguments['subheader']) {
            // one level below the header of the same record, unless the subheader has a tag of its own
            $headerTag = HeaderTag::forContent($this->arguments['tag'] ?? self::fieldOf($record, HeaderTag::FIELD), $layout, $this->arguments['default']);
            $tag = HeaderTag::forSubheader($headerTag, $this->arguments['subheaderTag'] ?? self::fieldOf($record, $tagField));
        } else {
            $tag = HeaderTag::forContent($this->arguments['tag'] ?? self::fieldOf($record, $tagField), $layout, $this->arguments['default']);
        }
        $this->tag->setTagName($tag);

        $classes = array_filter([
            trim((string) $this->tag->getAttribute('class')),
            HeaderTag::layoutClass($layout),
        ]);
        $this->tag->addAttribute('class', implode(' ', $classes));
        if ($this->isInspectRequest()) {
            // the content element this is rendered for, as the templates of fluid_styled_content have it ("data"; a
            // Content Block has it as a record object)
            $data = $this->renderingContext?->getVariableProvider()->get('data');
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
     * The value of a field of the record, NULL if there is no record or the field is not in it.
     */
    private static function fieldOf(?RecordInterface $record, string $field): mixed
    {
        return $record !== null && $record->has($field) ? $record->get($field) : null;
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
