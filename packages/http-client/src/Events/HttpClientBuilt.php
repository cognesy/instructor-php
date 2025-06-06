<?php

namespace Cognesy\Http\Events;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Utils\Events\Event;

final class HttpClientBuilt extends Event
{
    public function __construct(
        public string $driverClass,
        public HttpClientConfig $httpConfig,
        public DebugConfig $debugConfig,
        public array $middlewareStack,
    ) {
        parent::__construct();
    }

    public function toArray(): array {
        return [
            'driver' => $this->driverClass,
            'httpConfig' => $this->httpConfig->toArray(),
            'debugConfig' => $this->debugConfig->toArray(),
            'middlewareStack' => $this->middlewareStack,
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray());
    }
}