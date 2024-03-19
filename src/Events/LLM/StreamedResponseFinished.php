<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Events\Event;

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