<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\RecordInterface;
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
     */
    protected function output(string $tagName, mixed $layout, ?string $edit, ?int $element): string
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
        $this->tag->setContent((string) $this->renderChildren());

        return $this->tag->render();
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
