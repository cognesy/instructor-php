<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Data\ToolCall;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class StreamedToolCallUpdated extends Event
{
    public function __construct(
        public ToolCall $toolCall
    ){
        parent::__construct();
    }

    public function __toString() : string
    {
        return Json::encode($this->toolCall);
    }
}
