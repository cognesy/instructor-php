<?php

namespace Cognesy\Events\Utils;

use Cognesy\Events\Event;
use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use Psr\Log\LogLevel;

class EventFormatter
{
    public static function toShortName(Event $event) : string {
        $segments = explode('\\', (new \ReflectionClass($event))->getName());
        return array_pop($segments);
    }

    public static function toFullName(Event $event) : string {
        return (new \ReflectionClass($event))->getName();
    }

    /**
     * Formats the event string for the log file
     *
     * @param string $message The message to format
     * @return string The formatted log message
     */
    public static function logFormat(Event $event, string $message): string {
        $fullName = self::toFullName($event);
        return "({$event->id}) {$event->createdAt->format('Y-m-d H:i:s v')}ms ($event->logLevel) [$fullName] - $message";
    }

    /**
     * Formats the event string for the console
     *
     * @param string $message The message to format
     * @param bool $quote Whether to quote the message
     * @return string The formatted console message
     */
    public static function consoleFormat(Event $event, string $message = '', bool $quote = false) : string {
        $eventName = self::toShortName($event);
        if ($quote) {
            $message = Color::DARK_GRAY."`".Color::RESET.$message.Color::DARK_GRAY."`".Color::RESET;
        }
        return Console::columns([
            [7, '(.'.substr($event->id, -4).')'],
            [14, $event->createdAt->format('H:i:s v').'ms'],
            [7, "{$event->logLevel}", STR_PAD_LEFT],
            [30, "{$eventName}"],
            '-',
            [-1, $message],
        ], 140);
    }

    /**
     * Determines whether the event should be logged
     *
     * @param string $level The log level of the event
     * @param string $threshold The log level threshold
     * @return bool True if the event should be logged, false otherwise
     */
    public static function logFilter(string $level, string $threshold): bool {
        return self::logLevelRank($level) >= self::logLevelRank($threshold);
    }

    /**
     * Returns the rank of a log level as an integer.
     * Used for comparing severity of log levels.
     *
     * @param string $level The log level
     * @return int The rank of the log level
     */
    public static function logLevelRank(string $level): int {
        return match($level) {
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