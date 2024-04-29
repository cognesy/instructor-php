<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ApiRequestInitiated extends Event
{
    public function __construct(
        public ApiRequest $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode([
            'method' => $this->request->getMethod(),
            'endpoint' => $this->request->resolveEndpoint(),
            'body' => $this->request->body()->all(),
            'headers' => $this->request->headers()->all(),
        ]);
    }
}
