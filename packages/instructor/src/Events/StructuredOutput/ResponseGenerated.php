<?php

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

class ResponseGenerated extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public mixed $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}