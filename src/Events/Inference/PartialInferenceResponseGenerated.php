<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMApiResponse;

class PartialInferenceResponseGenerated extends Event
{
    public function __construct(
        public PartialLLMApiResponse $response,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->response->delta;
    }
}
