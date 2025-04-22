<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\Event;

/**
 * Event fired when a recorded HTTP interaction is replayed
 */
class HttpInteractionReplayed extends Event
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
            "[REPLAYED] %s %s => HTTP %d",
            $this->request->method(),
            $this->request->url(),
            $this->response->statusCode()
        );
    }
}
