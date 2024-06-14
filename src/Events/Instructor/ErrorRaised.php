<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Events\Event;
use Psr\Log\LogLevel;
use Throwable;

class ErrorRaised extends Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public Throwable    $error,
        public ?RequestInfo $request = null,
        public mixed        $context = null,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->error->getMessage();
    }
}
