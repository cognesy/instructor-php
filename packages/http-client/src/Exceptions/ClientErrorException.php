<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Throwable;

class ClientErrorException extends HttpRequestException
{
    private int $statusCode;

    public function __construct(
        int $statusCode,
        ?HttpRequest $request = null,
        ?HttpResponse $response = null,
        ?float $duration = null,
        ?Throwable $previous = null,
    ) {
        $this->statusCode = $statusCode;
        $message = sprintf('HTTP %d Client Error', $statusCode);
        parent::__construct($message, $request, $response, $duration, $previous);
    }
    
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
    
    public function isRetriable(): bool
    {
        return $this->statusCode === 429; // Only 429 Too Many Requests is retriable
    }
}