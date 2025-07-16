<?php declare(strict_types=1);

namespace Cognesy\Events;

use Cognesy\Events\Utils\EventFormatter;
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
        $this->data = match(true) {
            is_array($data) => $data,
            is_object($data) => get_object_vars($data),
            default => $data,
        };
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
        return EventFormatter::logFormat($this, (string) $this);
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
        return EventFormatter::consoleFormat($this, $message, $quote);
    }

    /**
     * Prints the event data to the console
     *
     * @param bool $quote Whether to quote the message
     * @param string $threshold The log level threshold
     */
    public function print(bool $quote = false, string $threshold = LogLevel::DEBUG): void {
        if (!EventFormatter::logFilter($threshold, $this->logLevel)) {
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
        return Json::encode($this->data);
    }

    /**
     * Returns the event data as an array
     *
     * @return array The event data as an array
     */
    public function toArray(): array {
        // TODO: make this better
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
}
