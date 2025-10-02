<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\BufferResponse;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

/**
 * Middleware that buffers HTTP responses for reuse
 * Ensures that response body and streams can be read multiple times
 */
class BufferResponseMiddleware extends BaseMiddleware
{
    #[\Override]
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool {
        return true;
    }

    #[\Override]
    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse {
        return new BufferResponseDecorator($request, $response);
    }
}
