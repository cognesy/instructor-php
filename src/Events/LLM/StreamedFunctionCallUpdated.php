<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\LLMs\FunctionCall;

class StreamedFunctionCallUpdated extends Event
{
    public function __construct(
        public FunctionCall $functionCall
    ){
        parent::__construct();
    }

    public function __toString() : string
    {
        return json_encode($this->functionCall);
    }
}
