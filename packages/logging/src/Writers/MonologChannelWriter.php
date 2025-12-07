<?php

declare(strict_types=1);

namespace Cognesy\Logging\Writers;

use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\LogEntry;
use Monolog\Logger;
use Psr\Log\LogLevel;

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
        $validLevel = $this->normalizeLogLevel($entry->level);

        // Create a logger for the specific channel if needed
        if ($this->useEntryChannel && $entry->channel !== $this->logger->getName()) {
            $channelLogger = $this->logger->withName($entry->channel);
            $channelLogger->log($validLevel, $entry->message, $entry->context);
        } else {
            $this->logger->log($validLevel, $entry->message, $entry->context);
        }
    }

    /**
     * Ensure the log level is a valid PSR-3 log level
     * @return LogLevel::*
     */
    private function normalizeLogLevel(string $level): string
    {
        $validLevels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        if (in_array($level, $validLevels, true)) {
            return $level;
        }

        // Default to info for unknown levels
        return LogLevel::INFO;
    }
}