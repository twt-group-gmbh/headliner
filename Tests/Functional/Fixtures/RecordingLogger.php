<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * A logger that keeps what it is given, to look at it in a test.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param array<mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
