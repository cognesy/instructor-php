<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\EventSource;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\StreamInterface;
use Closure;
use Generator;
use LengthException;

/**
 * Stream wrapper that notifies listeners on chunks and assembled events
 */
final class EventSourceStream implements StreamInterface
{
    private string $buffer = '';
    /**
     * Bytes of $buffer already emitted as events.
     *
     * The parser used to drop each consumed event with
     * `$buffer = substr($buffer, $pos + 2)`, reallocating everything still unparsed
     * once per event — 1,132 reallocations for a 205 KB SSE response. Tracking a
     * consumed prefix instead leaves the buffer alone; it is compacted in bulk once
     * the prefix gets large.
     */
    private int $consumed = 0;
    /** Consumed bytes tolerated before compactBuffer() pays for a copy. */
    private const COMPACT_THRESHOLD = 32768;
    private bool $completed = false;
    /** @var Closure(string): (string|bool)|null */
    private ?Closure $parser;

    /**
     * @param array<array-key,object> $listeners
     * @param callable(string): (string|bool)|null $parser
     */
    public function __construct(
        private StreamInterface $source,
        private ?HttpRequest $request,
        private ?HttpResponse $response,
        private array $listeners,
        ?callable $parser = null,
        private readonly int $maxBufferBytes = 1_048_576,
    ) {
        $this->parser = $parser !== null ? Closure::fromCallable($parser) : null;
    }

    #[\Override]
    public function getIterator(): \Traversable {
        $consumedFully = false;
        try {
            foreach ($this->source as $chunk) {
                // Only pay for normalisation when there is something to normalise.
                // Provider streams are LF-only in practice, so this usually skips.
                $this->buffer .= str_contains($chunk, "\r")
                    ? str_replace(["\r\n", "\r"], "\n", $chunk)
                    : $chunk;

                if ($this->request !== null && $this->response !== null) {
                    foreach ($this->listeners as $listener) {
                        $listener->onStreamChunkReceived($this->request, $this->response, $chunk);
                    }
                }

                while (($pos = strpos($this->buffer, "\n\n", $this->consumed)) !== false) {
                    $eventBlock = substr($this->buffer, $this->consumed, $pos - $this->consumed);
                    $this->consumed = $pos + 2;
                    yield from $this->emitEventBlock($eventBlock);
                }

                $this->compactBuffer();

                // The limit applies to what is still UNPARSED. Measuring the raw buffer
                // would make a long, perfectly-consumed stream trip a limit meant to
                // catch a single event block that never terminates.
                if (strlen($this->buffer) - $this->consumed > max(0, $this->maxBufferBytes)) {
                    throw new LengthException(sprintf(
                        'SSE parser buffer exceeded the configured limit of %d bytes.',
                        max(0, $this->maxBufferBytes),
                    ));
                }

                if ($this->parser === null) {
                    yield $chunk;
                }
            }
            if ($this->consumed < strlen($this->buffer)) {
                $eventBlock = substr($this->buffer, $this->consumed);
                $this->buffer = '';
                $this->consumed = 0;
                yield from $this->emitEventBlock($eventBlock);
            }
            $consumedFully = true;
        } finally {
            $this->completed = $consumedFully;
        }
    }

    /**
     * Drop the consumed prefix in bulk, once it is worth a copy.
     *
     * Compacting every event would reintroduce the per-event reallocation this class
     * exists to avoid; never compacting would grow the buffer to the whole response.
     * The threshold keeps both bounded: at most one copy per COMPACT_THRESHOLD bytes,
     * and at most that many stale bytes retained.
     */
    private function compactBuffer(): void {
        if ($this->consumed < self::COMPACT_THRESHOLD) {
            return;
        }
        $this->buffer = substr($this->buffer, $this->consumed);
        $this->consumed = 0;
    }

    /** @return Generator<string> */
    private function emitEventBlock(string $eventBlock): Generator {
        $payload = $this->parseSseEventBlock($eventBlock);
        if ($payload === '') {
            return;
        }

        if ($this->request !== null && $this->response !== null) {
            foreach ($this->listeners as $listener) {
                $listener->onStreamEventAssembled($this->request, $this->response, $payload);
            }
        }

        if ($this->parser === null) {
            return;
        }

        $mapped = ($this->parser)($payload);
        if (is_string($mapped) && $mapped !== '') {
            yield $mapped;
        }
        if ($mapped === true) {
            yield $payload;
        }
    }

    /**
     * Parse a single SSE event block per WHATWG EventSource spec:
     * - Lines of form "field: value" (value may be empty or have leading space)
     * - Multiple data: lines are joined with "\n"
     * - Lines starting with ':' are comments and ignored
     *
     * A block that is exactly one "data: ..." line takes a fast path — that is what
     * essentially every provider event looks like, and the general loop below costs an
     * explode plus two substr per line to reach the same answer.
     */
    private function parseSseEventBlock(string $block): string {
        if (!str_contains($block, "\n") && str_starts_with($block, 'data: ')) {
            return substr($block, 6);
        }

        $dataLines = [];
        $lines = explode("\n", $block);
        foreach ($lines as $line) {
            if ($line === '') { continue; }
            if ($line[0] === ':') { continue; }
            $sep = strpos($line, ':');
            if ($sep === false) {
                $field = $line;
                $value = '';
            } else {
                $field = substr($line, 0, $sep);
                $value = substr($line, $sep + 1);
                if (isset($value[0]) && $value[0] === ' ') {
                    $value = substr($value, 1);
                }
            }
            if ($field === 'data') {
                $dataLines[] = $value;
            }
            // We currently ignore 'event', 'id', 'retry' for listener payload
        }
        return implode("\n", $dataLines);
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->completed && $this->source->isCompleted();
    }
}
