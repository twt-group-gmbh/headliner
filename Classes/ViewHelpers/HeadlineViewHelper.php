<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use Twtgroup\Headliner\Frontend\HeaderTag;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Renders the header (or, with "subheader", the subheader) of a content element in the tag the editor
 * chose, with a CSS class for its layout, for example:
 *
 *   <headliner:headline tag="{data.header_tag}" layout="{data.header_layout}" default="{settings.defaultHeaderType}">
 *       {header}
 *   </headliner:headline>
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
        $this->registerArgument('edit', 'string', 'Where the tag can be edited, "table:uid:field"; only rendered for the page module');
        $this->registerArgument('subheaderTag', 'string', 'The tag chosen for the subheader; without it the subheader is one level below the header');
    }

    public function render(): string
    {
        $tag = HeaderTag::forContent($this->arguments['tag'], $this->arguments['layout'], $this->arguments['default']);
        $this->tag->setTagName($this->arguments['subheader'] ? HeaderTag::forSubheader($tag, $this->arguments['subheaderTag']) : $tag);

        $classes = array_filter([
            trim((string) $this->tag->getAttribute('class')),
            HeaderTag::layoutClass($this->arguments['layout']),
        ]);
        $this->tag->addAttribute('class', implode(' ', $classes));
        if ($this->arguments['edit'] && $this->isInspectRequest()) {
            $this->tag->addAttribute('data-headliner-edit', $this->arguments['edit']);
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
