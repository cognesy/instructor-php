<?php
namespace Cognesy\LLM\LLM\Events;

use Cognesy\Instructor\Events\Event;
use Cognesy\LLM\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Json\Json;

class PartialLLMResponseReceived extends Event
{
    public function __construct(
        public PartialLLMResponse $partialLLMResponse
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->partialLLMResponse);
    }
}
