<?php

namespace Cognesy\LLM\LLM\Events;

use Cognesy\LLM\LLM\InferenceRequest;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class InferenceRequested extends Event
{
    public function __construct(
        public InferenceRequest $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->request);
    }
}
