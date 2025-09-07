<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
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
        return match(true) {
            $statusCode >= 400 && $statusCode < 500 => new ClientErrorException(
                $statusCode, $request, $response, $duration, $previous
            ),
            $statusCode >= 500 => new ServerErrorException(
                $statusCode, $request, $response, $duration, $previous
            ),
            default => throw new InvalidArgumentException("Invalid HTTP status code: {$statusCode}"),
        };
    }
    
    public static function fromDriverException(
        Throwable $driverException,
        ?HttpRequest $request = null,
        ?float $duration = null,
    ): NetworkException {
        $message = $driverException->getMessage();
        
        return match(true) {
            // Connection errors - DNS resolution, refused connections, etc.
            str_contains($message, 'connect') ||
            str_contains($message, 'resolve') ||
            str_contains($message, 'refused') ||
            str_contains($message, 'Could not resolve host') ||
            str_contains($message, 'getaddrinfo failed') ||
            str_contains($message, 'Name or service not known') ||
            $driverException instanceof \GuzzleHttp\Exception\ConnectException
                => new ConnectionException($message, $request, $driverException),
            
            // Timeout errors - any kind of timeout
            str_contains($message, 'timeout') ||
            str_contains($message, 'timed out') ||
            str_contains($message, 'Operation timed out') ||
            $driverException instanceof \GuzzleHttp\Exception\TimeoutException
                => new TimeoutException($message, $request, $duration, $driverException),
                
            // Generic network error fallback
            default => new NetworkException($message, $request, null, $duration, $driverException),
        };
    }
}