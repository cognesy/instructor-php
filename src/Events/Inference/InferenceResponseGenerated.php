<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Events\Event;

class InferenceResponseGenerated extends Event
{
    public function __construct(
        public ApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->response->content;
    }
}
