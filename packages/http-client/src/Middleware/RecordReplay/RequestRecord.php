<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;

class RequestRecord
{
    private array $requestData;
    private array $responseData;

    public function __construct(array $requestData, array $responseData) {
        $this->requestData = $requestData;
        $this->responseData = $responseData;
    }

    public static function fromInteraction(HttpRequest $request, HttpResponse $response): self {
        if ($request->isStreamed()) {
            return StreamedRequestRecord::fromStreamedInteraction($request, $response);
        }

        $requestData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => $request->options(),
        ];

        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];

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

    public function matches(HttpRequest $request): bool {
        if ($this->requestData['url'] !== $request->url()) {
            return false;
        }

        if ($this->requestData['method'] !== $request->method()) {
            return false;
        }

        $requestBody = $request->body()->toString();
        if (!empty($requestBody) && !empty($this->requestData['body']) && $this->requestData['body'] !== $requestBody) {
            return false;
        }

        return true;
    }

    public function toResponse(bool $isStreaming = false): HttpResponse {
        // Non-streaming records should always return non-streaming responses
        return MockHttpResponse::success(
            $this->responseData['statusCode'] ?? 200,
            $this->responseData['headers'] ?? [],
            $this->responseData['body'] ?? '',
        );
    }

    public function isStreamed(): bool {
        return $this instanceof StreamedRequestRecord;
    }

    public static function createAppropriate(HttpRequest $request, HttpResponse $response): RequestRecord {
        if ($request->isStreamed()) {
            return StreamedRequestRecord::fromStreamedInteraction($request, $response);
        }

        return self::fromInteraction($request, $response);
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
