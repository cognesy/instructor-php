<?php declare(strict_types=1);

namespace Cognesy\Http\Data;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Stream\BufferedStream;
use Cognesy\Http\Stream\BufferedStreamInterface;

class HttpResponseData
{
    private int $statusCode;
    private string $body;
    private bool $isStreamed;
    private array $headers;

    private BufferedStreamInterface $stream;

    private function __construct(
        int $statusCode,
        string $body,
        array $headers,
        bool $isStreamed,
        null|iterable|BufferedStreamInterface $stream = null,
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->isStreamed = $isStreamed;
        $this->stream = match(true) {
            ($stream === null) => BufferedStream::empty(),
            ($stream instanceof BufferedStreamInterface) => $stream,
            (is_array($stream) === true) => BufferedStream::fromArray($stream),
            default => BufferedStream::fromStream($stream),
        };
    }

    public static function fromHttpResponse(HttpResponse $response) : self {
        $isStreamed = $response->isStreamed();
        return new self(
            statusCode: $response->statusCode(),
            body: $response->body(),
            headers: $response->headers(),
            isStreamed: $isStreamed,
            stream: $isStreamed ? $response->stream() : null,
        );
    }

    public static function empty() : self {
        return new self(
            statusCode: 0,
            body: '',
            headers: [],
            isStreamed: false,
            stream: BufferedStream::empty(),
        );
    }

    // ACCESSORS //////////////////////////////////////////

    public function statusCode(): int {
        return $this->statusCode;
    }

    public function headers(): array {
        return $this->headers;
    }

    public function body(): string {
        return $this->body;
    }

    public function isStreamed(): bool {
        return $this->isStreamed;
    }

    public function isStreaming(): bool {
        return !$this->stream->isCompleted();
    }

    /**
     * Read chunks of the stream
     *
     * @return iterable<string>
     */
    public function stream(): iterable {
        return $this->stream;
    }

    // SERIALIZATION //////////////////////////////////////////////////

    public static function fromArray(array $data) : self {
        return new self(
            statusCode: $data['statusCode'],
            body: $data['body'],
            headers: $data['headers'],
            isStreamed: $data['isStreamed'],
            stream: $data['stream'],
        );
    }

    public function toArray(): array {
        return [
            'statusCode' => $this->statusCode,
            'body' => $this->body,
            'headers' => $this->headers,
            'isStreamed' => $this->isStreamed,
            'stream' => $this->allChunks(),
        ];
    }

    // INTERNAL ///////////////////////////////////////////////////////

    private function allChunks() : array {
        $chunks = [];
        foreach ($this->stream as $chunk) {
            $chunks[] = $chunk;
        }
        return $chunks;
    }
}