<?php declare(strict_types=1);

namespace Cognesy\Http\Data;

use Cognesy\Http\Stream\BufferedStream;
use Cognesy\Http\Stream\NullStream;
use Cognesy\Http\Stream\StreamInterface;
use Generator;
use LogicException;

class HttpResponse
{
    private int $statusCode;
    private string $body;
    private bool $isStreamed;
    private array $headers;

    private StreamInterface $stream;

    public function __construct(
        int $statusCode,
        string $body,
        array $headers,
        bool $isStreamed,
        null|iterable|StreamInterface $stream = null,
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->isStreamed = $isStreamed;
        $this->stream = match (true) {
            ($stream === null) => BufferedStream::empty(),
            ($stream instanceof StreamInterface) => $stream,
            (is_array($stream) === true) => BufferedStream::fromArray($stream),
            default => BufferedStream::fromStream($stream),
        };
    }

    /**
     * Create a synchronous (non-streamed) response.
     *
     * @param int $statusCode
     * @param array<string, string>|array<string, array<string>> $headers
     * @param string $body
     */
    public static function sync(int $statusCode, array $headers, string $body): self {
        return new self(
            statusCode: $statusCode,
            body: $body,
            headers: $headers,
            isStreamed: false,
            stream: NullStream::instance(),
        );
    }

    /**
     * Create a streaming response with a provided stream implementation.
     *
     * @param int $statusCode
     * @param array<string, string>|array<string, array<string>> $headers
     * @param StreamInterface $stream
     */
    public static function streaming(int $statusCode, array $headers, StreamInterface $stream): self {
        return new self(
            statusCode: $statusCode,
            body: '',
            headers: $headers,
            isStreamed: true,
            stream: $stream,
        );
    }

    public static function empty(): self {
        return new self(
            statusCode: 0,
            body: '',
            headers: [],
            isStreamed: false,
            stream: NullStream::instance(),
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
        return match (true) {
            $this->isStreamed => throw new LogicException('Cannot access body of streamed response, use stream() instead.'),
            default => $this->body,
        };
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
     * @return Generator<string>
     */
    public function stream(): Generator {
        foreach ($this->stream as $data) {
            yield $this->toChunk($data);
        }
    }

    public function rawStream(): StreamInterface {
        return $this->stream;
    }

    /**
     * Return a new HTTP response instance with the same metadata and a replaced stream.
     * Does not alter the isStreamed flag or the body string.
     */
    public function withStream(StreamInterface $stream): self {
        return new self(
            statusCode: $this->statusCode,
            body: $this->body,
            headers: $this->headers,
            isStreamed: $this->isStreamed,
            stream: $stream,
        );
    }

    // SERIALIZATION //////////////////////////////////////////////////

    public static function fromArray(array $data): self {
        return new self(
            statusCode: $data['statusCode'],
            body: $data['body'],
            headers: $data['headers'],
            isStreamed: $data['isStreamed'],
            // stream is NOT serializable!
        );
    }

    public function toArray(): array {
        return [
            'statusCode' => $this->statusCode,
            'body' => $this->body,
            'headers' => $this->headers,
            'isStreamed' => $this->isStreamed,
            // stream is NOT serializable!
        ];
    }

    // INTERNAL ///////////////////////////////////////////////////////

    protected function toChunk(string $data): string {
        return $data;
    }
}
