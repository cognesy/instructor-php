<?php

namespace Cognesy\Polyglot\Http\Middleware\RecordReplay\Events;

use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\Event;

/**
 * Event fired when an HTTP interaction is recorded
 */
class HttpInteractionRecorded extends Event
{
    public function __construct(
        public readonly HttpClientRequest $request,
        public readonly HttpClientResponse $response
    ) {
        parent::__construct();
    }

    public function toConsole(): string
    {
        return sprintf(
            "[RECORDED] %s %s => HTTP %d",
            $this->request->method(),
            $this->request->url(),
            $this->response->statusCode()
        );
    }
}
