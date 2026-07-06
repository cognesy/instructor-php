<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;

/**
 * Shared driver-level event dispatch: one payload shape per event across all
 * HTTP drivers (Guzzle/Symfony/Curl/Mock). Consuming class must expose an
 * EventDispatcherInterface as $this->events.
 */
trait DispatchesHttpDriverEvents
{
    private function dispatchRequestSent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'requestId' => $request->id,
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            ...HttpRequestTelemetry::metadataForRequest($request),
        ]));
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'requestId' => $request->id,
            'url' => $request->url(),
            'method' => $request->method(),
            'statusCode' => $statusCode,
            ...HttpRequestTelemetry::metadataForRequest($request),
        ]));
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'requestId' => $request->id,
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $exception->getMessage(),
            ...HttpRequestTelemetry::metadataForRequest($request),
        ]));
    }

    private function dispatchResponseReceived(
        HttpRequest $request,
        int $statusCode,
        bool $isStreamed,
        ?string $body,
    ): void {
        $this->events->dispatch(new HttpResponseReceived(array_filter([
            'requestId' => $request->id,
            'statusCode' => $statusCode,
            'isStreamed' => $isStreamed,
            'body' => $isStreamed ? null : $body,
            ...HttpRequestTelemetry::metadataForRequest($request),
        ], static fn(mixed $v): bool => $v !== null)));
    }
}
