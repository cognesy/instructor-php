<?php

namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Config\LLMConfig;

final class InferenceDriverBuilt extends InferenceEvent
{
    public function __construct(
        public string $driverClass,
        public LLMConfig $config,
        public array $httpClientInfo,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driverClass,
            'config' => $this->config->toArray(),
            'httpClientInfo' => $this->httpClientInfo,
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray());
    }
}