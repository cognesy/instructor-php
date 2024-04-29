<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ApiStreamConnected extends Event
{
    public function __construct(
        public Response $response
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'status' => $this->response->status(),
        ]);
    }
}