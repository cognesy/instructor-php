<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Events\Event;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

/**
 * Event fired when a recorded HTTP interaction is replayed
 */
final class HttpInteractionReplayed extends Event
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
