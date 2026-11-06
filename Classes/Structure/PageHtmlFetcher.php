<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Loads the rendered frontend of a page, so headings from content elements, RTE content, other
 * extensions and backend layouts are included without special configuration.
 */
final readonly class PageHtmlFetcher
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private RequestFactory $requestFactory) {}

    /**
     * @return string|null The HTML, NULL if the page could not be loaded
     */
    public function fetch(UriInterface $uri): ?string
    {
        try {
            $response = $this->requestFactory->request((string) $uri, 'GET', [
                'http_errors' => false,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $response->getStatusCode() === 200 ? (string) $response->getBody() : null;
    }
}
