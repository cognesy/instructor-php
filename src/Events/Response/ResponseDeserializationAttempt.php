<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Utils\Json\Json;

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