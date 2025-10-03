<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Utils;

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
     * Processes data from a generator stream, dispatches events for received and parsed data,
     * and yields processed data.
     *
     * @param Generator $stream The input stream generator providing data to be processed.
     * @return Generator The generator yielding processed data after parsing.
     */
    public function eventsFrom(Generator $stream): Generator {
        foreach ($this->readLines($stream) as $line) {
            $this->events->dispatch(new StreamEventReceived($line));
            $processedData = $this->processLine($line);
            if ($processedData !== null) {
                $this->events->dispatch(new StreamEventParsed($processedData));
                yield $processedData;
            }
        }
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * Reads and extracts complete lines from a generator stream.
     *
     * @param Generator $stream The input stream generator providing chunks of data.
     * @return Generator A generator yielding complete lines of data ending with a newline character.
     */
    protected function readLines(Generator $stream): Generator {
        $buffer = '';
        foreach ($stream as $chunk) {
            $buffer .= $chunk;
            while (false !== ($pos = strpos($buffer, "\n"))) {
                yield substr($buffer, 0, $pos + 1);
                $buffer = substr($buffer, $pos + 1);
            }
        }
    }

    /**
     * Processes a single line of input, trims whitespace, attempts to parse it,
     * and optionally performs a debug dump if needed.
     *
     * @param string $line The input line to be processed.
     * @return string|null Returns the processed data as a string, or null if the line is empty or cannot be parsed.
     */
    protected function processLine(string $line): ?string {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        $data = $this->parse($line);
        if ($data === false || $data === true) {
            return null;
        }
        return $data;
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