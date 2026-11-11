<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Frontend\EditMark;
use Twtgroup\Headliner\Frontend\HeadingMark;
use Twtgroup\Headliner\Frontend\RowTable;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * The rich text of a row that came as an array (from a data processor), which knows nothing of its table or its field:
 *
 *   <headliner:text row="{item.data}" field="text"><f:format.html>{item.data.text}</f:format.html></headliner:text>
 *
 * `table` is found from the inline field of the content element, and `field` is the one rich text field of the table, if
 * the template does not say. For a visitor the content comes out as it is. For the request of the page module every heading in it names the
 * record and the field (`data-headliner-record`, `data-headliner-field`), so the box can change its tag in that text.
 * A record object needs none of this: its rich text is marked when the record is created (`MarkRichTextHeadings`).
 */
final class TextViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('row', 'array', 'The row as a data processor gives it', true);
        $this->registerArgument('field', 'string', 'The field of the row that holds the rich text. Without it the one rich text field of the table', false, '');
        $this->registerArgument('table', 'string', 'The table of the row, if the content element does not link it with an inline field', false, '');
    }

    public function render(): string
    {
        $content = (string) $this->renderChildren();
        $context = $this->renderingContext;
        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)
            || $context->getAttribute(ServerRequestInterface::class)->getAttribute(InspectRequest::ATTRIBUTE) !== true
        ) {
            return $content;
        }

        $row = $this->arguments['row'];
        $table = (string) $this->arguments['table'];
        if ($table === '') {
            $data = $context->getVariableProvider()->get('data');
            $table = is_array($data) ? (RowTable::of($row, $data) ?? '') : '';
        }
        $source = EditMark::rowSource($row, $table);
        $field = (string) $this->arguments['field'] !== '' ? (string) $this->arguments['field'] : self::richTextField($table);

        return $source === null || $field === '' ? $content : HeadingMark::inHtml($content, $source, $field);
    }

    /**
     * The one field of the table with a rich text; none if there is none or there are several (then `field` is needed).
     */
    private static function richTextField(string $table): string
    {
        $schemaFactory = GeneralUtility::makeInstance(TcaSchemaFactory::class);
        if (!$schemaFactory->has($table)) {
            return '';
        }
        $fields = [];
        foreach ($schemaFactory->get($table)->getFields() as $field) {
            if ($field instanceof TextFieldType && $field->isRichText()) {
                $fields[] = $field->getName();
            }
        }

        return count($fields) === 1 ? $fields[0] : '';
    }
}
