<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

/**
 * Event fired when an HTTP interaction is recorded
 */
final class HttpInteractionRecorded extends \Cognesy\Events\Event
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
