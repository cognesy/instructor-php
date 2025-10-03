<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;

/**
 * A specialized value object for handling streamed HTTP interactions.
 */
class StreamedRequestRecord extends RequestRecord
{
    private array $chunks = [];

    public function __construct(array $requestData, array $responseData, array $chunks = []) {
        parent::__construct($requestData, $responseData);
        $this->chunks = $chunks;
    }

    public static function fromStreamedInteraction(HttpRequest $request, HttpResponse $response): self {
        $requestData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => $request->options(),
        ];

        // Collect all chunks from the stream
        $chunks = [];
        $body = '';

        // Clone the generator to avoid consuming the original
        foreach ($response->stream() as $chunk) {
            $chunks[] = $chunk;
            $body .= $chunk;
        }

        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $body, // Store the full body too for non-streaming access
        ];

        return new self($requestData, $responseData, $chunks);
    }

    #[\Override]
    public static function fromJson(string $json): ?self {
        $data = json_decode($json, true);
        if (!$data || !isset($data['request']) || !isset($data['response'])) {
            return null;
        }
        $chunks = $data['chunks'] ?? [];
        return new self($data['request'], $data['response'], $chunks);
    }

    #[\Override]
    public function toJson(bool $prettyPrint = true): string {
        $data = [
            'request' => $this->getRequestData(),
            'response' => $this->getResponseData(),
            'chunks' => $this->chunks,
        ];
        return json_encode($data, $prettyPrint ? JSON_PRETTY_PRINT : 0) ?: '';
    }

    #[\Override]
    public function toResponse(bool $isStreaming = true): HttpResponse {
        if ($isStreaming) {
            return MockHttpResponse::streaming(
                $this->getStatusCode(),
                $this->getResponseHeaders(),
                $this->chunks,
            );
        }
        return MockHttpResponse::success(
            $this->getStatusCode(),
            $this->getResponseHeaders(),
            $this->getResponseBody(),
            $this->chunks,
        );
    }

    public function getChunks(): array {
        return $this->chunks;
    }

    public function getChunkCount(): int {
        return count($this->chunks);
    }

    public function hasChunks(): bool {
        return !empty($this->chunks);
    }

    public static function createAppropriateRecord(
        HttpRequest $request,
        HttpResponse $response,
    ): RequestRecord {
        if ($request->isStreamed()) {
            return self::fromStreamedInteraction($request, $response);
        }

        return RequestRecord::fromInteraction($request, $response);
    }

    #[\Override]
    protected function getRequestData(): array {
        // Access request data via reflection or other mechanism
        // This is a simplified implementation
        return [
            'url' => $this->getUrl(),
            'method' => $this->getMethod(),
            'body' => $this->getRequestBody(),
            // Add other request data as needed
        ];
    }

    #[\Override]
    protected function getResponseData(): array {
        // Access response data via reflection or other mechanism
        // This is a simplified implementation
        return [
            'statusCode' => $this->getStatusCode(),
            'body' => $this->getResponseBody(),
            'headers' => $this->getResponseHeaders(),
        ];
    }

    #[\Override]
    public function getResponseHeaders(): array {
        // This method would need to be implemented in the parent class as well
        // This is a simplified implementation
        return [];
    }
}
