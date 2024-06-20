<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ApiResponseReceived extends Event
{
    public function __construct(
        public int $status,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'status' => $this->status,
        ]);
    }
}