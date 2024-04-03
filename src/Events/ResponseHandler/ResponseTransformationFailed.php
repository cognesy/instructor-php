<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseTransformationFailed extends Event
{
    public function __construct(
        public CanTransformSelf $object,
        private string $message
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode([
            'message' => $this->message,
            'object' => $this->object
        ]);
    }
}