<?php

declare(strict_types=1);

namespace Cognesy\Logging\Filters;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFilter;
use Psr\Log\LogLevel;

/**
 * Filters events by minimum log level
 */
final readonly class LogLevelFilter implements EventFilter
{
    public function __construct(
        private string $minimumLevel = LogLevel::DEBUG
    ) {}

    public function __invoke(Event $event): bool
    {
        return $this->getLevelPriority($event->logLevel) <= $this->getLevelPriority($this->minimumLevel);
    }

    private function getLevelPriority(string $level): int
    {
        return match ($level) {
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
            default => 8,
        };
    }
}