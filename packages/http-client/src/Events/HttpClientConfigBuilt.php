<?php

namespace Cognesy\Http\Events;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Utils\Events\Event;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
final class HttpClientConfigBuilt extends Event
{
    public function __construct(
        public HttpClientConfig $httpClientConfig,
    ) {
        parent::__construct();
    }

    public function toArray(): array {
        return [
            'config' => $this->httpClientConfig->toArray(),
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}