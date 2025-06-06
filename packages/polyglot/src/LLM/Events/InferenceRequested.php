<?php

namespace Cognesy\Polyglot\LLM\Events;

use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Json\Json;

final class InferenceRequested extends InferenceEvent
{
    public function __construct(
        public InferenceRequest $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'request' => $this->request->toArray()
        ];
    }
}
