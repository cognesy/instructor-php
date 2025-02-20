<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class ResponseModelBuilt extends Event
{
    public function __construct(
        public ResponseModel $requestedModel
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->dumpVar($this->requestedModel));
    }
}