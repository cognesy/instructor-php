<?php

namespace Cognesy\Http\Events;

use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Utils\Events\Event;

final class HttpDriverBuilt extends Event
{
    public function __construct(
        public readonly string $clientClass,
        public readonly HttpClientConfig $config,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'clientClass' => $this->clientClass,
            'config' => $this->config->toArray(),
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}