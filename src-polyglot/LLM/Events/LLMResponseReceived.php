<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class LLMResponseReceived extends Event
{
    public function __construct(
        public LLMResponse $llmResponse,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->llmResponse);
    }
}
