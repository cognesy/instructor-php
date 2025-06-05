<?php

namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Events\Event;

class InferenceDriverBuilt extends Event
{
    public function __construct(
        public string $driver,
        public LLMConfig $config,
        public
    ) {
        parent::__construct();
    }
}