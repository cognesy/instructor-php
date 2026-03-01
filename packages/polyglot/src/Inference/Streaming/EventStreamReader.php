<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use Closure;
use Cognesy\Polyglot\Inference\Events\StreamEventParsed;
use Cognesy\Polyglot\Inference\Events\StreamEventReceived;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles reading and processing event streams.
 *
 * The EventStreamReader is responsible for reading data from a stream,
 * processing each line of input, and dispatching events for raw and
 * parsed data. It provides a mechanism for custom parsing of stream
 * data and integrates with an event dispatching system.
 */
class EventStreamReader
{
    protected EventDispatcherInterface $events;
    /** @var (Closure(string): (string|bool))|null */
    protected ?Closure $parser;

    /**
     * @param Closure(string): (string|bool)|null $parser
     */
    public function __construct(
        EventDispatcherInterface $events,
        ?Closure $parser = null,
    ) {
        $this->events = $events;
        $this->parser = $parser;
    }

    /**
     * Processes data from an iterable stream, dispatches events for received and parsed data,
     * and yields processed data.
     *
     * @param iterable $stream The input stream iterable providing data to be processed.
     * @return Generator The generator yielding processed data after parsing.
     */
    public function eventsFrom(iterable $stream): Generator {
        if ($this->parser !== null) {
            yield from $this->eventsFromSse($stream);
            return;
        }

        foreach ($this->readLines($stream) as $line) {
            $this->events->dispatch(new StreamEventReceived($line));
            $processedData = $this->processLine($line);
            if ($processedData === false) {
                break;
            }
            if (is_string($processedData)) {
                $this->events->dispatch(new StreamEventParsed($processedData));
                yield $processedData;
            }
        }
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * Parses stream as SSE events (blank-line delimited) and yields parser output.
     */
    protected function eventsFromSse(iterable $stream): Generator {
        foreach ($this->readSseEvents($stream) as $event) {
            $this->events->dispatch(new StreamEventReceived($event));
            $processedData = $this->processSseEvent($event);
            if ($processedData === false) {
                break;
            }
            if (is_string($processedData)) {
                $this->events->dispatch(new StreamEventParsed($processedData));
                yield $processedData;
            }
        }
    }

    /**
     * Reads and extracts complete lines from an iterable stream.
     *
     * @param iterable $stream The input stream iterable providing chunks of data.
     * @return Generator A generator yielding complete lines of data ending with a newline character.
     */
    protected function readLines(iterable $stream): Generator {
        $buffer = '';
        foreach ($stream as $chunk) {
            $buffer .= $chunk;
            while (false !== ($pos = strpos($buffer, "\n"))) {
                yield substr($buffer, 0, $pos + 1);
                $buffer = substr($buffer, $pos + 1);
            }
        }
        if ($buffer !== '') {
            yield $buffer;
        }
    }

    /**
     * Reads blank-line delimited SSE events from stream.
     *
     * @param iterable $stream
     * @return Generator<string>
     */
    protected function readSseEvents(iterable $stream): Generator {
        $eventLines = [];
        foreach ($this->readLines($stream) as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                if ($eventLines === []) {
                    continue;
                }

                yield implode("\n", $eventLines);
                $eventLines = [];
                continue;
            }

            if ($this->shouldFlushImplicitBoundary($eventLines, $line)) {
                yield implode("\n", $eventLines);
                $eventLines = [];
            }

            $eventLines[] = $line;
        }

        if ($eventLines === []) {
            return;
        }

        yield implode("\n", $eventLines);
    }

    /**
     * Compatibility heuristic for relays that emit line-delimited SSE data
     * without blank-line separators.
     *
     * @param list<string> $eventLines
     */
    protected function shouldFlushImplicitBoundary(array $eventLines, string $line): bool {
        if ($eventLines === []) {
            return false;
        }

        if (str_starts_with($line, 'event:') && $this->hasDataLine($eventLines)) {
            return true;
        }

        if (!str_starts_with($line, 'data:')) {
            return false;
        }

        if (!$this->isStandaloneDataLine($line)) {
            return false;
        }

        return $this->isStandaloneDataEvent($eventLines);
    }

    /**
     * @param list<string> $eventLines
     */
    protected function hasDataLine(array $eventLines): bool {
        foreach ($eventLines as $line) {
            if (str_starts_with($line, 'data:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $eventLines
     */
    protected function isStandaloneDataEvent(array $eventLines): bool {
        $hasData = false;
        foreach ($eventLines as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (!str_starts_with($line, 'data:')) {
                return false;
            }

            if (!$this->isStandaloneDataLine($line)) {
                return false;
            }

            $hasData = true;
        }

        return $hasData;
    }

    protected function isStandaloneDataLine(string $line): bool {
        if (!str_starts_with($line, 'data:')) {
            return false;
        }

        $payload = trim(substr($line, 5));
        if ($payload === '') {
            return false;
        }

        if ($payload === '[DONE]') {
            return true;
        }

        $lastChar = substr($payload, -1);
        if ($lastChar === '' || in_array($lastChar, [',', ':', '{', '[', '\\'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Processes a single line of input, trims whitespace, attempts to parse it,
     * and optionally performs a debug dump if needed.
     *
     * @param string $line The input line to be processed.
     * @return string|bool|null Returns processed data string, false for explicit stream termination, or null to skip.
     */
    protected function processLine(string $line): string|bool|null {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        $data = $this->parse($line);
        if ($data === false) {
            return false;
        }
        if ($data === true || $data === '') {
            return null;
        }
        return $data;
    }

    /**
     * Processes one SSE event and returns parser output.
     */
    protected function processSseEvent(string $event): string|bool|null {
        $dataLines = [];
        foreach (explode("\n", $event) as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (!str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $line, 2);
            if ($field !== 'data') {
                continue;
            }

            $dataLines[] = ltrim($value, ' ');
        }

        if ($dataLines === []) {
            return null;
        }

        return $this->processLine('data: ' . implode("\n", $dataLines));
    }

    /**
     * Parses a given input line using a custom parser if defined, or returns the line as is.
     *
     * @param string $line The input line to be parsed.
     * @return string|bool Returns the parsed line as a string if successful, or a boolean false on failure.
     */
    protected function parse(string $line): string|bool {
        return match(empty($this->parser)) {
            true => $line,
            false => ($this->parser)($line),
        };
    }
}
