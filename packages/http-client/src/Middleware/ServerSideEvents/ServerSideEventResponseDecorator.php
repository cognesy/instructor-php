<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\ServerSideEvents;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\TransformStream;

final class ServerSideEventResponseDecorator
{
    /**
     * Decorate response stream to yield SSE data payloads, optionally mapping each payload.
     *
     * @param callable(string): (string|bool)|null $parser
     */
    public static function decorate(
        HttpRequest $request,
        HttpResponse $response,
        ?callable $parser = null,
    ): HttpResponse {
        $sseStream = new ServerSideEventStream($response->rawStream());
        if ($parser === null) {
            return $response->withStream($sseStream);
        }
        $map = \Closure::fromCallable($parser);
        return $response->withStream(new TransformStream(
            source: $sseStream,
            transformFn: function (string $payload) use ($map): string {
                $out = $map($payload);
                return is_string($out) ? $out : '';
            }
        ));
    }
}
