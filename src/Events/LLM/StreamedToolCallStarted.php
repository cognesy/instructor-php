<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Data\ToolCall;
use Cognesy\Instructor\Events\Event;

class StreamedToolCallStarted extends Event
{
    public function __construct(
        public ToolCall $toolCall
    ){
        parent::__construct();
    }

    public function __toString() : string
    {
        return json_encode($this->toolCall);
    }
}
