<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseDeserializationAttempt extends Event
{
    public function __construct(
        public ResponseModel $responseModel,
        public string $json
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'json' => $this->json,
            'responseModel' => $this->responseModel
        ]);
    }
}