<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\ServerSideEvents;

use Cognesy\Http\Stream\StreamInterface;

/**
 * ServerSideEventStream parses a byte chunk stream into SSE data payloads.
 *
 * Yields the assembled "data" payload per SSE event (joining multiple data: lines with "\n").
 * Ignores comment lines and non-data fields for yielding purposes.
 */
final class ServerSideEventStream implements StreamInterface
{
    private string $buffer = '';
    private bool $completed = false;

    public function __construct(
        private StreamInterface $source,
    ) {}

    public function getIterator(): \Traversable {
        try {
            foreach ($this->source as $chunk) {
                $normalized = str_replace(["\r\n", "\r"], "\n", $chunk);
                $this->buffer .= $normalized;

                while (($pos = strpos($this->buffer, "\n\n")) !== false) {
                    $eventBlock = substr($this->buffer, 0, $pos);
                    $this->buffer = substr($this->buffer, $pos + 2);
                    $payload = $this->parseSseEventBlock($eventBlock);
                    if ($payload !== '') {
                        yield $payload;
                    }
                }
            }
        } finally {
            $this->completed = true;
        }
    }

    public function isCompleted(): bool {
        return $this->completed && $this->source->isCompleted();
    }

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
        }
        return implode("\n", $dataLines);
    }
}

