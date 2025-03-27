<?php

namespace Cognesy\Polyglot\Http\Middleware\BufferResponse;

use Cognesy\Polyglot\Http\BaseResponseDecorator;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Generator;

/**
 * Decorates HTTP responses with buffering capabilities
 * Stores response body and stream chunks for reuse
 */
class BufferResponseDecorator extends BaseResponseDecorator
{
    private ?string $bufferedBody = null;
    private array $bufferedChunks = [];
    private bool $isStreamBuffered = false;

    public function __construct(
        HttpClientRequest $request,
        HttpClientResponse $response,
    )
    {
        parent::__construct($request, $response);
    }

    public function body(): string
    {
        if ($this->bufferedBody === null) {
            $this->bufferedBody = $this->response->body();
        }
        return $this->bufferedBody;
    }

    public function stream(int $chunkSize = 1): Generator
    {
        if (!$this->isStreamBuffered) {
            foreach ($this->response->stream($chunkSize) as $chunk) {
                $this->bufferedChunks[] = $chunk;
                yield $chunk;
            }
            $this->isStreamBuffered = true;
        } else {
            foreach ($this->bufferedChunks as $chunk) {
                yield $chunk;
            }
        }
    }
}
