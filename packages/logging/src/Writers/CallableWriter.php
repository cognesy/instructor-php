<?php

declare(strict_types=1);

namespace Cognesy\Logging\Writers;

use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\LogEntry;

/**
 * Writer that delegates to a callable
 */
final readonly class CallableWriter implements LogWriter
{
    public function __construct(
        /** @var \Closure(LogEntry): void */
        private \Closure $writer,
    ) {}

    public function __invoke(LogEntry $entry): void
    {
        ($this->writer)($entry);
    }

    /**
     * @param callable(LogEntry): void $writer
     */
    public static function create(callable $writer): self
    {
        return new self($writer instanceof \Closure ? $writer : \Closure::fromCallable($writer));
    }
}