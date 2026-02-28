<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\ServerSideEvents;

use Cognesy\Http\Middleware\EventSource\EventSourceStream;
use Cognesy\Http\Stream\StreamInterface;

/**
 * Backward-compatible wrapper over EventSourceStream in parser mode.
 * Yields SSE data payloads as stream output.
 *
 * @deprecated Use EventSourceStream with parser callback instead.
 */
final class ServerSideEventStream implements StreamInterface
{
    private EventSourceStream $stream;

    public function __construct(
        private StreamInterface $source,
    ) {
        $this->stream = new EventSourceStream(
            source: $source,
            request: null,
            response: null,
            listeners: [],
            parser: static fn(string $payload) => $payload,
        );
    }

    #[\Override]
    public function getIterator(): \Traversable {
        yield from $this->stream;
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->stream->isCompleted();
    }
}
