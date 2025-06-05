<?php

namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Events\Event;

final class LLMConfigBuiltEvent extends Event
{
    public function __construct(
        public LLMConfig $config
    ) {
        parent::__construct();
    }

    public function toArray(): array {
        return [
            'config' => $this->config->toArray(),
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray());
    }
}