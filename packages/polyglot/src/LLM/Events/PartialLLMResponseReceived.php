<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class PartialLLMResponseReceived extends Event
{
    public function __construct(
        public PartialLLMResponse $partialLLMResponse
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->toArray());
    }

    public function toArray() : array {
        return [
            'partialLLMResponse' => $this->partialLLMResponse->toArray()
        ];
    }
}
