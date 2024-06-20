<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class ResponseTransformationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;

    public function __construct(
        public CanTransformSelf $object,
        private string $message
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'message' => $this->message,
            'object' => $this->object
        ]);
    }
}