<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Features\LLM\InferenceRequest;
use Cognesy\Instructor\Utils\Json\Json;

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
