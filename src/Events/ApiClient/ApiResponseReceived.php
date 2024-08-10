<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class ApiResponseReceived extends Event
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
        ]);
    }
}