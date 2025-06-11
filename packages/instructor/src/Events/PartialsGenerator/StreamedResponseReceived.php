<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Events\Event;
use Cognesy\Polyglot\LLM\Data\PartialInferenceResponse;
use Cognesy\Utils\Json\Json;

final class StreamedResponseReceived extends Event
{
    public function __construct(
        public PartialInferenceResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}