<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Throwable;

class ServerErrorException extends HttpRequestException
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
        $message = sprintf('HTTP %d Server Error', $statusCode);
        parent::__construct($message, $request, $response, $duration, $previous);
    }
    
    #[\Override]
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
    
    #[\Override]
    public function isRetriable(): bool
    {
        return true; // All 5xx errors are retriable
    }
}