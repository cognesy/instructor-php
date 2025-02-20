<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;
use Cognesy\LLM\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Json\Json;

class StreamedResponseReceived extends Event
{
    public function __construct(
        public PartialLLMResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}