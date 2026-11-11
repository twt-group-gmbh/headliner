<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers\Headline;

use TYPO3\CMS\Core\Domain\RecordInterface;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Frontend\HeaderTag;
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
        $this->registerArgument('tag', 'string', 'The tag chosen by the editor: h1 to h6 or div. With "subheader" it is the tag of the subheader');
        $this->registerArgument('layout', 'string', 'The layout of the header (header_layout, 1-6): adds the CSS class ce-headline-layout-N and, for content without a tag, decides the tag');
        $this->registerArgument('default', 'string', 'Used if there is no tag and no layout 1-6: a tag (h1-h6, div) or a layout number from 1 to 6. Without it the setting defaultHeaderType of the content element, else h2');
        $this->registerArgument('subheader', 'bool', 'A switch, subheader="1": the heading is the subheader of an element, not its header. Its tag is "tag", else "offset" levels below the header of "parent", which is then needed', false, false);
        $this->registerArgument('edit', 'string', 'Where the tag can be edited, "table:uid:field"; only rendered for the page module');
        $this->registerArgument('parent', RecordInterface::class . '|array', 'The content element, a record object or its array, whose header is the reference: a subheader, or a heading without a tag, is "offset" levels below it');
        $this->registerArgument('offset', 'int', 'How many levels below the header of "parent" (negative: above)', false, 1);
    }

    public function render(): string
    {
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
}
