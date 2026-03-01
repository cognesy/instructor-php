<?php declare(strict_types=1);

namespace Middleware\MiddlewareResponseDecorator;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

final class TrimChunkDecorator
{
    public static function decorate(HttpResponse $response): HttpResponse
    {
        return BaseResponseDecorator::decorate(
            $response,
            static fn(string $chunk): string => trim($chunk),
        );
    }
}
