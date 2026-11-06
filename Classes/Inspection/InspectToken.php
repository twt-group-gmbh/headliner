<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Inspection;

use TYPO3\CMS\Core\Crypto\HashService;

/**
 * The secret the page module sends in a header when it loads the frontend of a page. Only with it
 * the frontend marks the headings with the record they come from (see InspectRequest).
 */
final readonly class InspectToken
{
    public const HEADER = 'X-Headliner-Inspect';

    public function __construct(private HashService $hashService) {}

    public function create(): string
    {
        return $this->hashService->hmac('inspect', 'headliner');
    }

    public function isValid(string $token): bool
    {
        return $token !== '' && hash_equals($this->create(), $token);
    }
}
