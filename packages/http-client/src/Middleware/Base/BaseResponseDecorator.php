<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Base;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

/**
 * Class BaseResponseDecorator
 *
 * A base class for convenient decoration of HttpResponse objects
 * by overriding only the methods you need to change:
 * - statusCode() for the HTTP status code
 * - headers() for the HTTP headers
 * - contents() for the full response body
 * - streamContents() for streaming response chunks
 * - toChunk() to transform each chunk in a streamed response
 */
class BaseResponseDecorator implements HttpResponse
{
    public function __construct(
        protected HttpRequest  $request,
        protected HttpResponse $response,
    ) {}

    /**
     * Get the response status code
     *
     * @return int
     */
    #[\Override]
    public function statusCode(): int {
        return $this->response->statusCode();
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    #[\Override]
    public function headers(): array {
        return $this->response->headers();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    #[\Override]
    public function body(): string {
        return $this->response->body();
    }

    /**
     * Read chunks of the stream
     *
     * @param int|null $chunkSize
     * @return \Generator<string>
     */
    #[\Override]
    public function stream(?int $chunkSize = null): \Generator {
        foreach ($this->response->stream($chunkSize) as $chunk) {
            yield $this->toChunk($chunk);
        }
    }

    /**
     * Transform a chunk of streamed response content
     *
     * @param string $chunk
     * @return string
     */
    protected function toChunk(string $chunk): string {
        return $chunk;
    }

    #[\Override]
    public function isStreamed(): bool {
        return $this->response->isStreamed();
    }
}
