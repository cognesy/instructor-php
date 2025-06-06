<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class Debug
{
    private DebugConfig $config;
    /** @var CanHandleDebug[] */
    private array $handlers = [];

    public function __construct(DebugConfig $config) {
        $this->config = $config;
    }

    public function withConfig(DebugConfig $config): self {
        $this->config = $config;
        return $this;
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

    public function handleRequest(HttpClientRequest $request): void {
        foreach ($this->handlers as $handler) {
            $handler->handleRequest($request);
        }
    }

    public function handleResponse(HttpClientResponse $response) : void {
        foreach ($this->handlers as $handler) {
            $handler->handleResponse($response);
        }
    }

    public function handleStreamEvent(string $line) : void {
        foreach ($this->handlers as $handler) {
            $handler->handleStreamEvent($line);
        }
    }

    public function handleStreamChunk(string $chunk) : void {
        foreach ($this->handlers as $handler) {
            $handler->handleStreamChunk($chunk);
        }
    }

    public function isEnabled(): bool {
        return $this->config->httpEnabled;
    }

    public function config(): DebugConfig {
        return $this->config;
    }
}