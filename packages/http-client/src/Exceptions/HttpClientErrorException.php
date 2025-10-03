<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Throwable;

class HttpClientErrorException extends HttpRequestException
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
        $message = $this->buildMessage($statusCode, $response);
        parent::__construct($message, $request, $response, $duration, $previous);
    }

    private function buildMessage(int $statusCode, ?HttpResponse $response): string
    {
        $message = sprintf('HTTP %d Client Error', $statusCode);

        if ($response?->body()) {
            $body = $response->body();
            // Try to extract error message from JSON response
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $errorMsg = is_string($decoded['error'])
                    ? $decoded['error']
                    : ($decoded['error']['message'] ?? json_encode($decoded['error']));
                $message .= ": {$errorMsg}";
            } elseif (strlen($body) < 200) {
                $message .= ": {$body}";
            }
        }

        return $message;
    }
    
    #[\Override]
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
    
    #[\Override]
    public function isRetriable(): bool
    {
        return $this->statusCode === 429; // Only 429 Too Many Requests is retriable
    }
}