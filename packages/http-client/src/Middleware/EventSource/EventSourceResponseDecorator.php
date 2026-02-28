<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class EventSourceResponseDecorator
{
    /**
     * Create a new response stream with SSE parsing hooks.
     *
     * - with no parser: yields original raw chunks, notifies listeners
     * - with parser: yields parsed SSE data payloads transformed by parser
     *
     * @param callable(string): (string|bool)|null $parser
     */
    public static function decorate(
        HttpRequest $request,
        HttpResponse $response,
        array $listeners,
        ?callable $parser = null,
    ): HttpResponse {
        $eventSourceStream = new EventSourceStream(
            source: $response->rawStream(),
            request: $request,
            response: $response,
            listeners: $listeners,
            parser: $parser,
        );

        return $response->withStream($eventSourceStream);
    }
}
