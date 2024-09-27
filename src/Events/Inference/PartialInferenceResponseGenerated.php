<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Events\Event;

class PartialInferenceResponseGenerated extends Event
{
    public function __construct(
        public PartialApiResponse $response,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->response->delta;
    }
}
