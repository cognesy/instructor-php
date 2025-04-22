<?php
namespace Cognesy\Utils\Events;

use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use JsonSerializable;
use Psr\Log\LogLevel;

/**
 * Base class for all events
 */
class Event implements JsonSerializable
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public mixed $data;

    public $logLevel = LogLevel::DEBUG;

    public function __construct(mixed $data = []) {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->data = $data;
    }

    /**
     * Returns the name of the event class
     *
     * @return string The name of the event class
     */
    public function name() : string {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Returns event data as a log message
     *
     * @return string The event data as a log message
     */
    public function asLog(): string {
        return $this->logFormat((string) $this);
    }

    /**
     * Returns event data as a console message
     *
     * @param bool $quote Whether to quote the message
     * @return string The event data as a console message
     */
    public function asConsole(bool $quote = false): string {
        $message = (string) $this;
        $message = str_replace("\n", ' ', $message);
        return $this->consoleFormat($message, $quote);
    }

    /**
     * Prints the event data to the console
     *
     * @param bool $quote Whether to quote the message
     * @param string $threshold The log level threshold
     */
    public function print(bool $quote = false, string $threshold = LogLevel::DEBUG): void {
        if (!$this->logFilter($threshold, $this->logLevel)) {
            return;
        }
        echo $this->asConsole($quote)."\n";
    }

    /**
     * Prints the event data to the console
     */
    public function printLog(): void {
        echo $this->asLog()."\n";
    }

    /**
     * Prints the event debug data to the console
     */
    public function printDebug(): void {
        echo "\n".$this->asConsole()."\n";
        /** @noinspection ForgottenDebugOutputInspection */
        dump($this);
    }

    /**
     * Returns the event data as JSON string formatted for human readability
     *
     * @return string The event data as a JSON string
     */
    public function __toString(): string {
        return Json::encode($this->data, JSON_PRETTY_PRINT);
    }

    /**
     * Returns the event data as an array
     *
     * @return array The event data as an array
     */
    public function toArray(): array {
        return json_decode(json_encode($this), true);
    }

    /**
     * Returns the event data as JSON string for serialization
     *
     * @return string The event data as a JSON string
     */
    public function jsonSerialize() : array {
        return get_object_vars($this);
    }

    /// PRIVATE ////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Formats the event string for the log file
     *
     * @param string $message The message to format
     * @return string The formatted log message
     */
    private function logFormat(string $message): string {
        $class = (new \ReflectionClass($this))->getName();
        return "({$this->id}) {$this->createdAt->format('Y-m-d H:i:s v')}ms ($this->logLevel) [$class] - $message";
    }

    /**
     * Formats the event string for the console
     *
     * @param string $message The message to format
     * @param bool $quote Whether to quote the message
     * @return string The formatted console message
     */
    private function consoleFormat(string $message = '', bool $quote = false) : string {
        $segments = explode('\\', (new \ReflectionClass($this))->getName());
        $eventName = array_pop($segments);
        //$eventGroup = implode('\\', $segments);
        if ($quote) {
            $message = Color::DARK_GRAY."`".Color::RESET.$message.Color::DARK_GRAY."`".Color::RESET;
        }
        return Console::columns([
            [7, '(.'.substr($this->id, -4).')'],
            [14, $this->createdAt->format('H:i:s v').'ms'],
            [7, "{$this->logLevel}", STR_PAD_LEFT],
            [30, "{$eventName}"],
            '-',
            [-1, $message],
        ], 140);
    }

    /**
     * Dumps a variable to an array
     *
     * @param mixed $var The variable to dump
     * @return array The variable as an array
     */
    protected function dumpVar(mixed $var) : array {
        if (is_scalar($var)) {
            return [$var];
        }
        if (is_array($var)) {
            return $var;
        }
        $reflection = new \ReflectionObject($var);
        $properties = array();
        foreach($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            if (!$property->isInitialized($var)) {
                $properties[$property->getName()] = null;
            } else {
                $properties[$property->getName()] = $property->getValue($var);
            }
        }
        return $properties;
    }

    /**
     * Determines whether the event should be logged
     *
     * @param string $level The log level of the event
     * @param string $threshold The log level threshold
     * @return bool True if the event should be logged, false otherwise
     */
    protected function logFilter(string $level, string $threshold): bool {
        return $this->logLevelRank($level) >= $this->logLevelRank($threshold);
    }

    /**
     * Returns the rank of a log level as an integer.
     * Used for comparing severity of log levels.
     *
     * @param string $level The log level
     * @return int The rank of the log level
     */
    protected function logLevelRank(string $level): int {
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
