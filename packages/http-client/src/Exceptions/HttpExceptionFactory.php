<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use InvalidArgumentException;
use Throwable;

class HttpExceptionFactory
{
    public static function fromStatusCode(
        int $statusCode,
        ?HttpRequest $request = null,
        ?HttpResponse $response = null,
        ?float $duration = null,
        ?Throwable $previous = null,
    ): HttpRequestException {
        return match (true) {
            $statusCode >= 400 && $statusCode < 500 => new HttpClientErrorException(
                $statusCode, $request, $response, $duration, $previous,
            ),
            $statusCode >= 500 => new ServerErrorException(
                $statusCode, $request, $response, $duration, $previous,
            ),
            default => throw new InvalidArgumentException("Invalid HTTP status code: {$statusCode}"),
        };
    }
}