<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Unit\Structure\Fixtures\RecordingRequestHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

final class InspectRequestTest extends TestCase
{
    private function handler(): RecordingRequestHandler
    {
        return new RecordingRequestHandler(static fn(): Response => new Response());
    }

    #[Test]
    public function leavesARequestWithoutTheMarkAlone(): void
    {
        $handler = $this->handler();
        $request = new ServerRequest();

        (new InspectRequest())->process($request, $handler);

        self::assertSame($request, $handler->receivedRequest());
        self::assertNull($handler->receivedRequest()->getAttribute('frontend.cache.instruction'));
    }

    #[Test]
    public function aHeaderFromOutsideDoesNotMarkTheRequest(): void
    {
        $handler = $this->handler();
        $request = (new ServerRequest())->withHeader('X-Headliner-Inspect', 'anything');

        (new InspectRequest())->process($request, $handler);

        self::assertNull($handler->receivedRequest()->getAttribute('frontend.cache.instruction'));
        self::assertNull($handler->receivedRequest()->getAttribute(InspectRequest::ATTRIBUTE));
    }

    #[Test]
    public function switchesTheCacheOffForAMarkedRequest(): void
    {
        $handler = $this->handler();
        $request = (new ServerRequest())->withAttribute(InspectRequest::ATTRIBUTE, true);

        (new InspectRequest())->process($request, $handler);

        $instruction = $handler->receivedRequest()->getAttribute('frontend.cache.instruction');
        self::assertInstanceOf(CacheInstruction::class, $instruction);
        self::assertFalse($instruction->isCachingAllowed());
        self::assertTrue($handler->receivedRequest()->getAttribute(InspectRequest::ATTRIBUTE));
    }

    #[Test]
    public function usesTheCacheInstructionThatIsAlreadyThere(): void
    {
        $handler = $this->handler();
        $existing = new CacheInstruction();
        $request = (new ServerRequest())
            ->withAttribute(InspectRequest::ATTRIBUTE, true)
            ->withAttribute('frontend.cache.instruction', $existing);

        (new InspectRequest())->process($request, $handler);

        self::assertSame($existing, $handler->receivedRequest()->getAttribute('frontend.cache.instruction'));
        self::assertFalse($existing->isCachingAllowed());
    }
}
