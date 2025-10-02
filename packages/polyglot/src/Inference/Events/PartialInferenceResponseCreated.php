<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
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

    #[\Override]
    public function toArray() : array {
        return [
            'partialInferenceResponse' => $this->partialInferenceResponse->toArray()
        ];
    }
}
