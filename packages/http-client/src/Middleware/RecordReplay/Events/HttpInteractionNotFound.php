<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Events\Event;
use Cognesy\Http\Data\HttpClientRequest;

/**
 * Event fired when a recording is not found for a request
 */
final class HttpInteractionNotFound extends Event
{
    public function __construct(
        public readonly HttpClientRequest $request
    ) {
        parent::__construct();
    }

    public function toConsole(): string
    {
        return sprintf(
            "[NOT FOUND] %s %s",
            $this->request->method(),
            $this->request->url()
        );
    }
}
