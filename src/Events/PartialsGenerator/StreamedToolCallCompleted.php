<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\ToolCall;
use Cognesy\Instructor\Utils\Json\Json;

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
