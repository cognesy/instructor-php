<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Core\ResponseModel;
use Cognesy\Instructor\Events\Event;

class ResponseModelBuilt extends Event
{
    public function __construct(
        public ResponseModel $requestedModel
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->format(json_encode($this->requestedModel));
    }
}