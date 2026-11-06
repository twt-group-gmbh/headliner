<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twtgroup\Headliner\Inspection\InspectToken;
use Twtgroup\Headliner\Structure\PageHtmlFetcher;
use Twtgroup\Headliner\Tests\Unit\Structure\Fixtures\RecordingRequestHandler;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PageHtmlFetcherTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $globalsBackup = [];

    protected function setUp(): void
    {
        $this->globalsBackup = [
            'BE_USER' => $GLOBALS['BE_USER'] ?? null,
            'TYPO3_REQUEST' => $GLOBALS['TYPO3_REQUEST'] ?? null,
            'encryptionKey' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? null,
            'EXEC_TIME' => $GLOBALS['EXEC_TIME'] ?? null,
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'test-key';
        // the date aspect of the Context reads it; the application sets it in real life
        $GLOBALS['EXEC_TIME'] = time();
        GeneralUtility::setSingletonInstance(Context::class, new Context());
    }

    protected function tearDown(): void
    {
        GeneralUtility::resetSingletonInstances([]);
        $GLOBALS['BE_USER'] = $this->globalsBackup['BE_USER'];
        $GLOBALS['TYPO3_REQUEST'] = $this->globalsBackup['TYPO3_REQUEST'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->globalsBackup['encryptionKey'];
        $GLOBALS['EXEC_TIME'] = $this->globalsBackup['EXEC_TIME'];
    }

    private function token(): InspectToken
    {
        return new InspectToken(new HashService());
    }

    private function handler(\Closure $callback): RecordingRequestHandler
    {
        return new RecordingRequestHandler($callback);
    }

    #[Test]
    public function returnsTheBodyOfASuccessfulResponse(): void
    {
        $handler = $this->handler(static function (): ResponseInterface {
            $response = new Response();
            $response->getBody()->write('<h1>Hi</h1>');

            return $response;
        });

        self::assertSame('<h1>Hi</h1>', (new PageHtmlFetcher($handler, $this->token()))->fetch(new Uri('https://example.com/')));
    }

    #[Test]
    public function requestsThePageWithGetAndTheInspectToken(): void
    {
        $handler = $this->handler(static fn(): ResponseInterface => new Response());

        (new PageHtmlFetcher($handler, $this->token()))->fetch(new Uri('https://example.com/about'));

        self::assertNotNull($handler->received);
        self::assertSame('GET', $handler->received->getMethod());
        self::assertSame('https://example.com/about', (string) $handler->received->getUri());
        self::assertTrue($this->token()->isValid($handler->received->getHeaderLine(InspectToken::HEADER)));
    }

    #[Test]
    public function returnsNothingForAResponseThatIsNotOk(): void
    {
        $handler = $this->handler(static fn(): ResponseInterface => new Response('php://temp', 404));

        self::assertNull((new PageHtmlFetcher($handler, $this->token()))->fetch(new Uri('https://example.com/')));
    }

    #[Test]
    public function returnsNothingIfTheFrontendThrows(): void
    {
        $handler = $this->handler(static function (): ResponseInterface {
            throw new \RuntimeException('No page configured for type=0');
        });

        self::assertNull((new PageHtmlFetcher($handler, $this->token()))->fetch(new Uri('https://example.com/')));
    }

    #[Test]
    public function putsBackTheStateTheFrontendChanged(): void
    {
        $backendUser = new \stdClass();
        $originalRequest = new ServerRequest();
        $GLOBALS['BE_USER'] = $backendUser;
        $GLOBALS['TYPO3_REQUEST'] = $originalRequest;
        $context = GeneralUtility::makeInstance(Context::class);
        $backendAspect = new UserAspect();
        $visibilityAspect = new VisibilityAspect(true, true);
        $context->setAspect('backend.user', $backendAspect);
        $context->setAspect('visibility', $visibilityAspect);

        $handler = $this->handler(static function (ServerRequestInterface $request) use ($context): ResponseInterface {
            // what the frontend middleware stack does to the state of the surrounding backend request
            $GLOBALS['BE_USER'] = null;
            $GLOBALS['TYPO3_REQUEST'] = $request;
            $context->setAspect('backend.user', new UserAspect());
            $context->setAspect('visibility', new VisibilityAspect());

            return new Response();
        });

        (new PageHtmlFetcher($handler, $this->token()))->fetch(new Uri('https://example.com/'));

        self::assertSame($backendUser, $GLOBALS['BE_USER']);
        self::assertSame($originalRequest, $GLOBALS['TYPO3_REQUEST']);
        self::assertSame($backendAspect, $context->getAspect('backend.user'));
        self::assertSame($visibilityAspect, $context->getAspect('visibility'));
    }

    #[Test]
    public function putsBackTheStateEvenIfTheFrontendThrows(): void
    {
        $backendUser = new \stdClass();
        $GLOBALS['BE_USER'] = $backendUser;

        $handler = $this->handler(static function (): ResponseInterface {
            $GLOBALS['BE_USER'] = null;
            throw new \RuntimeException('boom');
        });

        (new PageHtmlFetcher($handler, $this->token()))->fetch(new Uri('https://example.com/'));

        self::assertSame($backendUser, $GLOBALS['BE_USER']);
    }
}
