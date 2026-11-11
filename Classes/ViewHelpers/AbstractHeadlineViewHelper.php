<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\InputFieldType;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Schema\TagField;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * What the two ViewHelpers for a heading do the same: the tag, the class for the layout and, for the request of the
 * page module only, the marks. Only h1-h6 and div can come out, so a value can never inject markup.
 */
abstract class AbstractHeadlineViewHelper extends AbstractTagBasedViewHelper
{
    protected $escapeOutput = false;

    /**
     * The content element of the template, as the templates of fluid_styled_content (an array) and a Content Block (a
     * record object) have it.
     */
    protected function contentElement(): mixed
    {
        return $this->renderingContext?->getVariableProvider()->get('data');
    }

    /**
     * The setting `defaultHeaderType` of the content element, if the template has it.
     */
    protected function defaultTag(): mixed
    {
        return $this->renderingContext?->getVariableProvider()->getByPath('settings.defaultHeaderType');
    }

    /**
     * The value of a field of a record object or of the array of a record, NULL if there is none.
     */
    protected static function valueOf(mixed $source, string $field): mixed
    {
        return match (true) {
            $source instanceof RecordInterface => $source->has($field) ? $source->get($field) : null,
            is_array($source) => $source[$field] ?? null,
            default => null,
        };
    }

    protected static function isRecord(mixed $source): bool
    {
        return $source instanceof RecordInterface || is_array($source);
    }

    /**
     * The tag `$levels` below the header of a record or of the array of one: its tag, else its layout, else the default.
     */
    protected static function below(mixed $header, int $levels, mixed $default): string
    {
        return HeaderTag::shifted(
            HeaderTag::forContent(self::valueOf($header, HeaderTag::FIELD), self::valueOf($header, 'header_layout'), $default),
            $levels
        );
    }

    /**
     * @param string|null $edit Where the tag is edited, "table:uid:field"; only rendered for the page module
     * @param int|null $element The content element the heading is rendered in; only rendered for the page module
     * @param string|null $content The text of the heading; the content of the tag if there is none
     */
    protected function output(string $tagName, mixed $layout, ?string $edit, ?int $element, ?string $content = null): string
    {
        $this->tag->setTagName($tagName);
        $classes = array_filter([
            trim((string) $this->tag->getAttribute('class')),
            HeaderTag::layoutClass($layout),
        ]);
        $this->tag->addAttribute('class', implode(' ', $classes));
        if ($this->isInspectRequest()) {
            if ($edit) {
                $this->tag->addAttribute('data-headliner-edit', $edit);
            }
            // a heading names its element itself: a Content Block has no frame with an id around its element
            if ($element !== null) {
                $this->tag->addAttribute('data-headliner-element', (string) $element);
            }
        }
        $this->tag->ignoreEmptyAttributes(true);
        $this->tag->setContent($content ?? (string) $this->renderChildren());

        return $this->tag->render();
    }

    /**
     * The heading of a record: the tag, the layout, the text and the marks for the page module come from it. The arguments
     * it reads are the same in the ViewHelpers for a record and for a row: subheader, tag, tagField, textField, default, parent
     * and offset.
     */
    protected function renderRecord(RecordInterface $record): string
    {
        $data = $this->contentElement();
        $subheader = (bool) $this->arguments['subheader'];
        $tagField = (string) ($this->arguments['tagField'] ?? '') !== '' ? (string) $this->arguments['tagField'] : self::tagFieldOf($record, $subheader);
        $own = $this->arguments['tag'] ?? self::valueOf($record, $tagField);
        // a record of another table (a card) is counted from the header of the content element, if the template has it
        $parent = $this->arguments['parent'] ?? (!$subheader && $record->getMainType() !== 'tt_content' && self::isRecord($data) ? $data : null);
        $default = $this->arguments['default'] ?? $this->defaultTag();
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
            $textField = (string) ($this->arguments['textField'] ?? '');
            $content = $this->textOf($record, $textField !== '' ? $textField : ($subheader ? 'subheader' : self::labelFieldOf($record)));
            if ($content === '') {
                // nothing to say: no empty heading
                return '';
            }
        }

        return $this->output($tag, $layout, EditMark::forRecord($record, $tagField), EditMark::elementUid($data) ?? EditMark::elementUid($record), $content);
    }

    /**
     * The label field of the table of the record, if it is a plain text (an input, or a text that is no rich text): the
     * title of a card, the header of a content element. Nothing for a table without one or with another kind of field (a
     * select, a number), as that is no text for a heading.
     */
    private static function labelFieldOf(RecordInterface $record): string
    {
        $schemaFactory = GeneralUtility::makeInstance(TcaSchemaFactory::class);
        $table = $record->getMainType();
        if (!$schemaFactory->has($table)) {
            return '';
        }
        $schema = $schemaFactory->get($table);
        if (!$schema->hasCapability(TcaSchemaCapability::Label)) {
            return '';
        }
        $name = (string) $schema->getCapability(TcaSchemaCapability::Label)->getPrimaryFieldName();
        if ($name === '' || !$schema->hasField($name)) {
            return '';
        }
        $field = $schema->getField($name);

        return $field instanceof InputFieldType || ($field instanceof TextFieldType && !$field->isRichText()) ? $name : '';
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

    private function isInspectRequest(): bool
    {
        $context = $this->renderingContext;
        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)) {
            return false;
        }

        return $context->getAttribute(ServerRequestInterface::class)->getAttribute(InspectRequest::ATTRIBUTE) === true;
    }
}
