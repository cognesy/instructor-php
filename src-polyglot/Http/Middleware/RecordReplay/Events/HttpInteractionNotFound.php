<?php

namespace Cognesy\Polyglot\Http\Middleware\RecordReplay\Events;

use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\Event;

/**
 * Event fired when a recording is not found for a request
 */
class HttpInteractionNotFound extends Event
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
