<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class Debug
{
    /** @var CanHandleDebug[] */
    private array $handlers = [];

    public function __construct(CanHandleDebug ...$handlers) {
        $this->withHandlers(...$handlers);
    }

    public function withHandlers(CanHandleDebug ...$handlers): self {
        foreach ($handlers as $handler) {
            $this->handlers[] = match(true) {
                $handler instanceof CanHandleDebug => $handler,
                default => throw new \InvalidArgumentException('Handler must implement CanHandleDebug interface.')
            };
        }
        return $this;
    }

    public function handleStream(string $line, bool $isConsolidated = false): void {
        foreach ($this->handlers as $handler) {
            $handler->handleStream($line, $isConsolidated);
        }
    }

    public function handleRequest(HttpClientRequest $request): void {
        foreach ($this->handlers as $handler) {
            $handler->handleRequest($request);
        }
    }

    public function handleResponse(HttpClientResponse $response, array $options) {
        foreach ($this->handlers as $handler) {
            $handler->handleResponse($response, $options);
        }
    }
}