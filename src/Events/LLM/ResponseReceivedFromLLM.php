<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Events\Event;

class ResponseReceivedFromLLM extends Event
{
    public function __construct(
        public ApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}