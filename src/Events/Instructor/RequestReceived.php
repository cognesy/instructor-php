<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class RequestReceived extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public Request $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->dumpVar($this->request));
    }
}