<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Base;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

/**
 * Class BaseResponseDecorator
 *
 * A base class for convenient decoration of HttpResponse objects by
 * transforming the stream at construction time. This aligns with
 * HttpResponse being a concrete data object with a buffered stream.
 *
 * Subclasses can override one of:
 * - chunkMap(string $chunk): string  // simple 1:1 per-chunk transform
 * - transformStream(iterable $source): iterable<string> // advanced pipeline
 */
class BaseResponseDecorator extends HttpResponse
{
    public function __construct(
        protected HttpRequest $request,
        protected HttpResponse $response,
    ) {
        parent::__construct(
            statusCode: $response->statusCode(),
            body: $response->body(),
            headers: $response->headers(),
            isStreamed: $response->isStreamed(),
            stream: $this->transformStream($response->stream()),
        );
    }

    // INTERNAL ///////////////////////////////////////////////////

    /**
     * Transform single chunk of data.
     */
    protected function toChunk(string $data): string {
        return $data;
    }

    /**
     * Transform the source stream into a new iterable. Default maps per chunk.
     *
     * @param iterable<string> $source
     * @return iterable<string>
     */
    protected function transformStream(iterable $source): iterable {
        foreach ($source as $chunk) {
            yield $this->toChunk($chunk);
        }
    }
}
