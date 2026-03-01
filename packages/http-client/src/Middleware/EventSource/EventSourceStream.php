<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\StreamInterface;
use Closure;

/**
 * Stream wrapper that notifies listeners on chunks and assembled events
 */
final class EventSourceStream implements StreamInterface
{
    private string $buffer = '';
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
    ) {
        $this->parser = $parser !== null ? Closure::fromCallable($parser) : null;
    }

    #[\Override]
    public function getIterator(): \Traversable {
        try {
            foreach ($this->source as $chunk) {
                $normalized = str_replace(["\r\n", "\r"], "\n", $chunk);
                $this->buffer .= $normalized;

                if ($this->request !== null && $this->response !== null) {
                    foreach ($this->listeners as $listener) {
                        $listener->onStreamChunkReceived($this->request, $this->response, $chunk);
                    }
                }

                while (($pos = strpos($this->buffer, "\n\n")) !== false) {
                    $eventBlock = substr($this->buffer, 0, $pos);
                    $this->buffer = substr($this->buffer, $pos + 2);
                    $payload = $this->parseSseEventBlock($eventBlock);
                    if ($payload === '') {
                        continue;
                    }

                    if ($this->request !== null && $this->response !== null) {
                        foreach ($this->listeners as $listener) {
                            $listener->onStreamEventAssembled($this->request, $this->response, $payload);
                        }
                    }

                    if ($this->parser === null) {
                        continue;
                    }

                    $mapped = ($this->parser)($payload);
                    if (is_string($mapped) && $mapped !== '') {
                        yield $mapped;
                    }
                    if ($mapped === true) {
                        yield $payload;
                    }
                }

                if ($this->parser === null) {
                    yield $chunk;
                }
            }
        } finally {
            $this->completed = true;
        }
    }

    /**
     * Parse a single SSE event block per WHATWG EventSource spec:
     * - Lines of form "field: value" (value may be empty or have leading space)
     * - Multiple data: lines are joined with "\n"
     * - Lines starting with ':' are comments and ignored
     */
    private function parseSseEventBlock(string $block): string {
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
