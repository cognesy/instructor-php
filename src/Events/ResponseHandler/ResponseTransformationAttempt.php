<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseTransformationAttempt extends Event
{
    public function __construct(
        public CanTransformSelf $object
    ) {
        parent::__construct($object);
    }

    public function __toString(): string
    {
        return Json::encode($this->object);
    }
}