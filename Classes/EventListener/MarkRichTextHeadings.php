<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\Event\RecordCreationEvent;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use Twtgroup\Headliner\Middleware\InspectRequest;

/**
 * Names the record and the field in every heading of a rich text, for the page module's own request only
 * (`InspectRequest`): `<h3 data-headliner-record="table:uid" data-headliner-field="bodytext">`. The mark is put into the
 * field value when the record is created, so it does not matter how a template renders the text, and it needs no
 * wrapper (`headliner:headline.children`) around it.
 */
#[AsEventListener(identifier: 'headliner/mark-rich-text-headings')]
final class MarkRichTextHeadings
{
    public function __invoke(RecordCreationEvent $event): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface || $request->getAttribute(InspectRequest::ATTRIBUTE) !== true) {
            return;
        }

        $raw = $event->getRawRecord();
        $source = $raw->getMainType() . ':' . ($raw->getComputedProperties()->getLocalizedUid() ?? $raw->getUid());
        $schema = $event->getSchema();
        foreach ($event->getProperties() as $name => $value) {
            if (!is_string($value) || $value === '' || !$schema->hasField($name)) {
                continue;
            }
            $field = $schema->getField($name);
            if (!$field instanceof TextFieldType || !$field->isRichText()) {
                continue;
            }
            $event->setProperty($name, self::mark($value, $source, $name));
        }
    }

    private static function mark(string $html, string $source, string $field): string
    {
        $attributes = ' data-headliner-record="' . htmlspecialchars($source) . '" data-headliner-field="' . htmlspecialchars($field) . '"';

        return (string) preg_replace('/<(h[1-6])(?=[\s>])/i', '<$1' . $attributes, $html);
    }
}
