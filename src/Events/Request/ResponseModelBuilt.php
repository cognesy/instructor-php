<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

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
        return Json::encode($this->requestedModel);
    }
}