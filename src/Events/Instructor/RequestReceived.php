<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\RequestData;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class RequestReceived extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public RequestData $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->dumpVar($this->request));
    }
}