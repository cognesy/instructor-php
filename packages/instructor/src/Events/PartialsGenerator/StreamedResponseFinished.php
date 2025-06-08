<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Events\Event;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Json\Json;

final class StreamedResponseFinished extends Event
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