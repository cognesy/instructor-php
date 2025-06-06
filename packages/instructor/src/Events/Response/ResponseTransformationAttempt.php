<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class ResponseTransformationAttempt extends Event
{
    public function __construct(
        public CanTransformSelf $object
    ) {
        parent::__construct($object);
    }

    public function __toString(): string {
        return Json::encode($this->object);
    }
}