<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;

/**
 * Shared driver-level event dispatch: one payload shape per event across all
 * HTTP drivers (Guzzle/Symfony/Curl/Mock). Consuming class must expose an
 * EventDispatcherInterface as $this->events.
 */
trait DispatchesHttpDriverEvents
{
    private function dispatchRequestSent(HttpRequest $request): void {
        $requestData = $this->safeRequestData($request);
        $this->events->dispatch(new HttpRequestSent([
            'requestId' => $request->id,
            'url' => $requestData['url'],
            'method' => $request->method(),
            'headers' => $requestData['headers'],
            'requestBodyBytes' => strlen($request->body()->toString()),
            ...HttpRequestTelemetry::metadataForRequest($request),
        ]));
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request): void {
        $requestData = $this->safeRequestData($request);
        $this->events->dispatch(new HttpRequestFailed([
            'requestId' => $request->id,
            'url' => $requestData['url'],
            'method' => $request->method(),
            'headers' => $requestData['headers'],
            'requestBodyBytes' => strlen($request->body()->toString()),
            'statusCode' => $statusCode,
            ...HttpRequestTelemetry::metadataForRequest($request),
        ]));
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void {
        $requestData = $this->safeRequestData($request);
        $this->events->dispatch(new HttpRequestFailed([
            'requestId' => $request->id,
            'url' => $requestData['url'],
            'method' => $request->method(),
            'headers' => $requestData['headers'],
            'requestBodyBytes' => strlen($request->body()->toString()),
            'statusCode' => $exception->getStatusCode(),
            'errors' => DefaultRequestRedactor::redactBody(
                DefaultRequestRedactor::redactUrl($exception->getMessage()),
            ),
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
            'responseBodyBytes' => $isStreamed || $body === null ? null : strlen($body),
            ...HttpRequestTelemetry::metadataForRequest($request),
        ], static fn(mixed $v): bool => $v !== null)));
    }

    /** @return array{url: string, headers: array<string, mixed>} */
    private function safeRequestData(HttpRequest $request): array {
        return [
            'url' => DefaultRequestRedactor::redactUrl($request->url()),
            'headers' => DefaultRequestRedactor::redactHeaders($request->headers()),
        ];
    }
}
