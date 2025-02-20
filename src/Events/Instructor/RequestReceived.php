<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Utils\Events\Event;
use Psr\Log\LogLevel;

class RequestReceived extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return 'OK';
    }
}
