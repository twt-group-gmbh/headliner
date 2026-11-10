<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers\Headline;

use Psr\Http\Message\ServerRequestInterface;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Frontend\RowTable;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Wraps what is rendered for one record that is not the content element itself (a card of a Content Block), so the
 * page module knows that the headings in it belong to that record, and to which content element:
 *
 *   <f:for each="{data.cards}" as="card">
 *       <headliner:headline.children record="{card}">
 *           <headliner:headline tag="{card.tag}">{card.title}</headliner:headline>
 *           <f:format.html>{card.text}</f:format.html>
 *       </headliner:headline.children>
 *   </f:for>
 *
 * A row as an array (a data processor gives it) goes in `row` instead of `record`, with `table="..."` if the content
 * element does not link it with an inline field; it is better to turn it into a record object with the core's
 * `record-transformation` data processor, see the documentation.
 *
 * The page module then finds the field of every heading itself: the rich text it is written in, or the field with its
 * tag. So the headline needs no `edit`.
 *
 * For a visitor what is inside comes out as it is. Only for the request of the page module it is wrapped in an element
 * (`display: contents`) with the marks.
 */
final class ChildrenViewHelper extends AbstractTagBasedViewHelper
{
    /** Set while the content of the record is rendered: a headline in it is not the header of the element */
    public const VARIABLE = 'headlinerInRecord';

    protected $tagName = 'div';

    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('record', 'mixed', 'The record the content is rendered for, as a Content Block gives it (a record object)');
        $this->registerArgument('row', 'array', 'Instead of `record`: the row as an array, as a data processor gives it');
        $this->registerArgument('table', 'string', 'The table of `row`, if the content element does not link it with an inline field', false, '');
    }

    public function render(): string
    {
        $context = $this->renderingContext;
        $variables = $context?->getVariableProvider();
        $wasInRecord = $variables?->exists(self::VARIABLE) === true;
        $variables?->add(self::VARIABLE, true);
        try {
            $content = (string) $this->renderChildren();
        } finally {
            if (!$wasInRecord) {
                $variables?->remove(self::VARIABLE);
            }
        }
        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)
            || $context->getAttribute(ServerRequestInterface::class)->getAttribute(InspectRequest::ATTRIBUTE) !== true
        ) {
            return $content;
        }
        $data = $context->getVariableProvider()->get('data');
        $row = $this->arguments['row'];
        if (is_array($row)) {
            // without a table the one the element links the row from
            $table = (string) $this->arguments['table'];
            $table = $table !== '' ? $table : (is_array($data) ? RowTable::of($row, $data) ?? '' : '');
            $record = EditMark::rowSource($row, $table);
        } else {
            $record = EditMark::recordSource($this->arguments['record']);
        }
        if ($record === null) {
            return $content;
        }

        $this->tag->addAttribute('data-headliner-record', $record);
        $element = EditMark::elementUid($data);
        if ($element !== null) {
            $this->tag->addAttribute('data-headliner-element', (string) $element);
        }
        $this->tag->addAttribute('style', 'display: contents');
        $this->tag->setContent($content);

        return $this->tag->render();
    }
}
