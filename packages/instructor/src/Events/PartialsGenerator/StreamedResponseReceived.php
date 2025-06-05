<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class StreamedResponseReceived extends Event
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