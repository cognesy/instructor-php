<?php
namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Json\Json;

final class InferenceResponseCreated extends InferenceEvent
{
    public function __construct(
        public InferenceResponse $inferenceResponse,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->inferenceResponse->toArray());
    }

    public function toArray() : array {
        return [
            'inferenceResponse' => $this->inferenceResponse->toArray()
        ];
    }
}
