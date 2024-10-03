<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;

class InferenceResponseGenerated extends Event
{
    public function __construct(
        public LLMApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->response->content;
    }
}
