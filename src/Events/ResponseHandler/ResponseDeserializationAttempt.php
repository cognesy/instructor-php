<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Event;

class ResponseDeserializationAttempt extends Event
{
    public function __construct(
        public ResponseModel $responseModel,
        public string $json)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode([
            'json' => $this->json,
            'responseModel' => $this->responseModel
        ]);
    }
}