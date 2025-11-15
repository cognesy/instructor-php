<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class EventSourceResponseDecorator
{
    /**
     * Create a new response whose stream notifies listeners on chunk and event boundaries.
     */
    public static function decorate(
        HttpRequest $request,
        HttpResponse $response,
        array $listeners,
    ): HttpResponse {
        $eventSourceStream = new EventSourceStream(
            source: $response->rawStream(),
            request: $request,
            response: $response,
            listeners: $listeners,
        );

        return $response->withStream($eventSourceStream);
    }
}
