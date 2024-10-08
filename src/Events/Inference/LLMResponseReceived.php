<?php
namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Utils\Json\Json;

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
