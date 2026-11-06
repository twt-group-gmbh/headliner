<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Twtgroup\Headliner\Backend\RteChange;
use Twtgroup\Headliner\Backend\RteHeadingEditor;

/**
 * AJAX route that changes the tag of a heading in the rich text of a content element, for the select in the box of the
 * page module. The rights are checked by `RteHeadingEditor` and the DataHandler.
 */
final readonly class RteHeadingController
{
    public function __construct(private RteHeadingEditor $editor) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $result = $this->editor->change(
            (string) ($body['table'] ?? 'tt_content'),
            (int) ($body['uid'] ?? 0),
            (string) ($body['field'] ?? RteHeadingEditor::FIELD),
            (int) ($body['ordinal'] ?? -1),
            (string) ($body['tag'] ?? ''),
            (string) ($body['text'] ?? '')
        );

        return new JsonResponse(['result' => $result->value], match ($result) {
            RteChange::Changed => 200,
            RteChange::NotAllowed => 403,
            RteChange::NotFound => 404,
            RteChange::Mismatch => 409,
            RteChange::Failed => 500,
        });
    }
}
