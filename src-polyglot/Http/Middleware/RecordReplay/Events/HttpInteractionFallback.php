<?php

namespace Cognesy\Polyglot\Http\Middleware\RecordReplay\Events;

use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\Event;

/**
 * Event fired when falling back to a real request because no recording was found
 */
class HttpInteractionFallback extends Event
{
    public function __construct(
        public readonly HttpClientRequest $request
    ) {
        parent::__construct();
    }

    public function toConsole(): string
    {
        return sprintf(
            "[FALLBACK] %s %s",
            $this->request->method(),
            $this->request->url()
        );
    }
}
