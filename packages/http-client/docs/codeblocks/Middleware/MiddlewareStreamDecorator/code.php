<?php declare(strict_types=1);

namespace Middleware\MiddlewareStreamDecorator;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Middleware\MiddlewareResponseDecorator\TrimChunkDecorator;

final class TrimChunkMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool
    {
        return $response->isStreamed();
    }

    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        return TrimChunkDecorator::decorate($response);
    }
}
