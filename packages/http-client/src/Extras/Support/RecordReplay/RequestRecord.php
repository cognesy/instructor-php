<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\RequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\ResponseRedactor;

/** @deprecated Use cassette interactions through RecordReplayMiddleware. */
class RequestRecord
{
    private array $requestData;
    private array $responseData;

    public function __construct(array $requestData, array $responseData) {
        $this->requestData = $requestData;
        $this->responseData = $responseData;
    }

    public static function fromInteraction(HttpRequest $request, HttpResponse $response, ?RequestRedactor $redactor = null): self {
        if ($response->isStreamed()) {
            return StreamedRequestRecord::fromStreamedInteraction($request, $response, $redactor);
        }

        $requestData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => $request->options(),
        ];
        if ($redactor !== null) {
            $requestData = $redactor->redact($requestData);
        }

        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];
        if ($redactor instanceof ResponseRedactor) {
            $responseData = $redactor->redactResponse($responseData);
        }

        return new self($requestData, $responseData);
    }

    public static function fromJson(string $json): ?self {
        $data = json_decode($json, true);

        if (!$data || !isset($data['request']) || !isset($data['response'])) {
            return null;
        }

        if (isset($data['chunks'])) {
            return StreamedRequestRecord::fromJson($json);
        }

        return new self($data['request'], $data['response']);
    }

    public function toJson(bool $prettyPrint = true): string {
        $data = [
            'request' => $this->requestData,
            'response' => $this->responseData,
        ];

        return json_encode($data, $prettyPrint ? JSON_PRETTY_PRINT : 0) ?: '';
    }

    public function toResponse(bool $isStreaming = false): HttpResponse {
        if ($isStreaming) {
            $body = $this->responseData['body'] ?? '';
            $chunks = $body === '' ? [] : [$body];
            return MockHttpResponseFactory::streaming(
                $this->responseData['statusCode'] ?? 200,
                $this->responseData['headers'] ?? [],
                $chunks,
            );
        }

        return MockHttpResponseFactory::success(
            $this->responseData['statusCode'] ?? 200,
            $this->responseData['headers'] ?? [],
            $this->responseData['body'] ?? '',
        );
    }

    public function isStreamed(): bool {
        return $this instanceof StreamedRequestRecord;
    }

    public static function createAppropriate(HttpRequest $request, HttpResponse $response, ?RequestRedactor $redactor = null): RequestRecord {
        if ($response->isStreamed()) {
            return StreamedRequestRecord::fromStreamedInteraction($request, $response, $redactor);
        }

        return self::fromInteraction($request, $response, $redactor);
    }

    public function getUrl(): string {
        return $this->requestData['url'] ?? '';
    }

    public function getMethod(): string {
        return $this->requestData['method'] ?? '';
    }

    public function getRequestBody(): string {
        return $this->requestData['body'] ?? '';
    }

    public function getResponseBody(): string {
        return $this->responseData['body'] ?? '';
    }

    public function getStatusCode(): int {
        return $this->responseData['statusCode'] ?? 200;
    }

    public function getResponseHeaders(): array {
        return $this->responseData['headers'] ?? [];
    }

    protected function getRequestData(): array {
        return $this->requestData;
    }

    protected function getResponseData(): array {
        return $this->responseData;
    }
}
