<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\RequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\ResponseRedactor;

/**
 * A specialized value object for handling streamed HTTP interactions.
 *
 * @deprecated Use cassette interactions through RecordReplayMiddleware.
 */
class StreamedRequestRecord extends RequestRecord
{
    /** @var list<string> */
    private array $chunks = [];

    public function __construct(array $requestData, array $responseData, array $chunks = []) {
        parent::__construct($requestData, $responseData);
        $this->chunks = $chunks;
    }

    public static function fromStreamedInteraction(HttpRequest $request, HttpResponse $response, ?RequestRedactor $redactor = null): self {
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

        // Chunks are the canonical body store; getResponseBody() derives from them.
        $chunks = [];
        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
        ];

        if ($response->isStreamed()) {
            foreach ($response->stream() as $chunk) {
                $chunks[] = $chunk;
            }
        } else {
            $body = $response->body();
            if ($body !== '') {
                $chunks[] = $body;
            }
        }

        if ($redactor instanceof ResponseRedactor) {
            $redacted = $redactor->redactResponse([
                ...$responseData,
                'chunks' => $chunks,
            ]);
            $responseData = array_diff_key($redacted, ['chunks' => true]);
            $chunks = is_array($redacted['chunks'] ?? null) ? array_values(array_filter(
                $redacted['chunks'],
                static fn(mixed $chunk): bool => is_string($chunk),
            )) : $chunks;
        }

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
        $responseData = $this->getResponseData();
        // Legacy records stored the body both in response data and as chunks.
        // Chunks are canonical — drop the duplicate when it carries no extra info.
        if (($responseData['body'] ?? '') === implode('', $this->chunks)) {
            unset($responseData['body']);
        }

        $data = [
            'request' => $this->getRequestData(),
            'response' => $responseData,
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

    #[\Override]
    public function getResponseBody(): string {
        $body = parent::getResponseBody();

        return match ($body) {
            '' => implode('', $this->chunks),
            default => $body,
        };
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
