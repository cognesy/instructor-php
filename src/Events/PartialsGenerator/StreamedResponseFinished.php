<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Utils\Json\Json;

class StreamedResponseFinished extends Event
{
    public function __construct(
        public PartialLLMResponse $response
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}