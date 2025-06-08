<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Events\Event;
use Cognesy\Polyglot\LLM\Data\ToolCall;
use Cognesy\Utils\Json\Json;

final class StreamedToolCallCompleted extends Event
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
