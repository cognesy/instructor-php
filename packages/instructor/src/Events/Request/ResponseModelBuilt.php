<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class ResponseModelBuilt extends Event
{
    public function __construct(
        public ResponseModel $responseModel
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'responseModel' => $this->responseModel->toArray(),
        ];
    }
}