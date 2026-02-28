<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;

/**
 * A specialized value object for handling streamed HTTP interactions.
 */
class StreamedRequestRecord extends RequestRecord
{
    /** @var list<string> */
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

        $chunks = [];
        $body = '';
        if ($response->isStreamed()) {
            foreach ($response->stream() as $chunk) {
                $chunks[] = $chunk;
                $body .= $chunk;
            }
        } else {
            $body = $response->body();
            if ($body !== '') {
                $chunks[] = $body;
            }
        }

        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $body,
        ];

        return new self($requestData, $responseData, $chunks);
    }

    #[\Override]
    public static function fromJson(string $json): ?self {
        $data = json_decode($json, true);
        if (!$data || !isset($data['request']) || !isset($data['response'])) {
            return null;
        }
        $chunks = array_values(array_filter($data['chunks'] ?? [], is_string(...)));
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
            return MockHttpResponseFactory::streaming(
                $this->getStatusCode(),
                $this->getResponseHeaders(),
                $this->chunks,
            );
        }
        return MockHttpResponseFactory::success(
            $this->getStatusCode(),
            $this->getResponseHeaders(),
            $this->getResponseBody(),
            [], // Don't pass chunks for non-streaming response
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

    /**
     * @deprecated Use RequestRecord::createAppropriate() instead.
     */
    public static function createAppropriateRecord(
        HttpRequest $request,
        HttpResponse $response,
    ): RequestRecord {
        if ($response->isStreamed()) {
            return self::fromStreamedInteraction($request, $response);
        }

        return RequestRecord::fromInteraction($request, $response);
    }
}
