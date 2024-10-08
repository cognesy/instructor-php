<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;

class PartialInferenceResponseGenerated extends Event
{
    public function __construct(
        public PartialLLMResponse $response,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->response->delta;
    }
}
