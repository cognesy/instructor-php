<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Utils\Json\Json;

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
