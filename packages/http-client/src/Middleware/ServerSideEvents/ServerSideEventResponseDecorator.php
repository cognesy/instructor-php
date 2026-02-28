<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\ServerSideEvents;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\EventSource\EventSourceResponseDecorator;

/**
 * @deprecated Use EventSourceResponseDecorator directly.
 */
final class ServerSideEventResponseDecorator
{
    /**
     * Decorate response stream to yield SSE data payloads, optionally mapping each payload.
     *
     * @param callable(string): (string|bool)|null $parser
     * @deprecated Use EventSourceResponseDecorator directly.
     */
    public static function decorate(
        HttpRequest $request,
        HttpResponse $response,
        ?callable $parser = null,
    ): HttpResponse {
        return EventSourceResponseDecorator::decorate(
            request: $request,
            response: $response,
            listeners: [],
            parser: $parser ?? static fn(string $payload) => $payload,
        );
    }
}
