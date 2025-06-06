<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Json\Json;

final class PartialLLMResponseCreated extends InferenceEvent
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
