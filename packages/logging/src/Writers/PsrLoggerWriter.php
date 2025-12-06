<?php

declare(strict_types=1);

namespace Cognesy\Logging\Writers;

use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\LogEntry;
use Psr\Log\LoggerInterface;

/**
 * Writer that outputs to any PSR-3 compatible logger
 */
final readonly class PsrLoggerWriter implements LogWriter
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LogEntry $entry): void
    {
        $this->logger->log(
            level: $entry->level,
            message: $entry->message,
            context: $entry->context,
        );
    }
}