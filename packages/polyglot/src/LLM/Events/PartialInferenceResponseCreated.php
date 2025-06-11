<?php
namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\Data\PartialInferenceResponse;
use Cognesy\Utils\Json\Json;

final class PartialInferenceResponseCreated extends InferenceEvent
{
    public function __construct(
        public PartialInferenceResponse $partialInferenceResponse
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->toArray());
    }

    public function toArray() : array {
        return [
            'partialInferenceResponse' => $this->partialInferenceResponse->toArray()
        ];
    }
}
