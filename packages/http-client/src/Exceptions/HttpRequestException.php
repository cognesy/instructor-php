<?php declare(strict_types=1);

namespace Cognesy\Http\Exceptions;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Exception;
use Throwable;

class HttpRequestException extends Exception
{
    protected ?HttpRequest $request;
    protected ?HttpResponse $response;
    protected ?float $duration;

    public function __construct(
        string $message,
        ?HttpRequest $request = null,
        ?HttpResponse $response = null,
        ?float $duration = null,
        ?Throwable $previous = null,
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->duration = $duration;
        parent::__construct($this->formatMessage($message), 0, $previous);
    }

    public function getRequest(): ?HttpRequest
    {
        return $this->request;
    }

    public function getResponse(): ?HttpResponse
    {
        return $this->response;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function isRetriable(): bool
    {
        return false; // Conservative default
    }

    public function getStatusCode(): ?int
    {
        return $this->response?->statusCode();
    }

    protected function formatMessage(string $message): string
    {
        $parts = [$message];
        
        if ($this->request) {
            $parts[] = sprintf('Request: %s %s', $this->request->method(), $this->request->url());
        }
        
        if ($this->response) {
            $parts[] = sprintf('Status: %d', $this->response->statusCode());
        }
        
        if ($this->duration) {
            $parts[] = sprintf('Duration: %.2fs', $this->duration);
        }
        
        return implode('. ', $parts);
    }
}
