<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\PartialInferenceResponse;
use Cognesy\Utils\Json\Json;

final class PartialLLMResponseCreated extends InferenceEvent
{
    public function __construct(
        public PartialInferenceResponse $partialLLMResponse
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
