<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;

class InferenceResponseGenerated extends Event
{
    public function __construct(
        public LLMResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->response->content;
    }
}
