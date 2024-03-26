<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Events\Event;

class StreamedResponseReceived extends Event
{
    public function __construct(
        public PartialApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}