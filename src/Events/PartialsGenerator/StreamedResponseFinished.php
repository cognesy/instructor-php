<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class StreamedResponseFinished extends Event
{
    public function __construct(
        public ApiResponse $response
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}