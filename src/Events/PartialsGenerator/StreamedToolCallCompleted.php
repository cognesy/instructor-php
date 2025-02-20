<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;
use Cognesy\LLM\LLM\Data\ToolCall;
use Cognesy\Utils\Json\Json;

class StreamedToolCallCompleted extends Event
{
    public function __construct(
        public ToolCall $toolCall
    ){
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->toolCall);
    }
}
