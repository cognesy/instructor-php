<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\LLMs\Data\LLMResponse;

class StreamedResponseFinished extends Event
{
    public function __construct(
        public LLMResponse $response
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}