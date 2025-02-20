<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

class ResponseDeserialized extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public mixed $object
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->object);
    }
}