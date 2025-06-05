<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class ResponseValidationAttempt extends Event
{
    public function __construct(
        public object $response
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'response' => $this->response,
        ];
    }
}