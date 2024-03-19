<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Data\ResponseModel;
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
        return json_encode($this->requestedModel);
    }
}