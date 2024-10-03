<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMApiResponse;
use Cognesy\Instructor\Utils\Json\Json;

class StreamedResponseReceived extends Event
{
    public function __construct(
        public PartialLLMApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}