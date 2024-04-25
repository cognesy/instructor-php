<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class StreamedResponseReceived extends Event
{
    public function __construct(
        public PartialApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}