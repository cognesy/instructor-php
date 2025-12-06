<?php

declare(strict_types=1);

namespace Cognesy\Logging\Writers;

use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\LogEntry;
use Monolog\Logger;

/**
 * Writer that outputs to Monolog with channel-based routing
 */
final readonly class MonologChannelWriter implements LogWriter
{
    public function __construct(
        private Logger $logger,
        private bool $useEntryChannel = true,
    ) {}

    public function __invoke(LogEntry $entry): void
    {
        // Create a logger for the specific channel if needed
        if ($this->useEntryChannel && $entry->channel !== $this->logger->getName()) {
            $channelLogger = $this->logger->withName($entry->channel);
            $channelLogger->log($entry->level, $entry->message, $entry->context);
        } else {
            $this->logger->log($entry->level, $entry->message, $entry->context);
        }
    }
}