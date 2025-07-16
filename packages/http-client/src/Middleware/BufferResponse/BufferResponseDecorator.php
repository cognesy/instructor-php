<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\BufferResponse;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

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
        HttpRequest  $request,
        HttpResponse $response,
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

    public function stream(?int $chunkSize = null): iterable
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
