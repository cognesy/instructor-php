<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Http\Data\HttpClientRequest;

/**
 * Event fired when falling back to a real request because no recording was found
 */
final class HttpInteractionFallback extends \Cognesy\Events\Event
{
    public function __construct(
        public readonly HttpClientRequest $request
    ) {
        parent::__construct();
    }

    public function toConsole(): string {
        return sprintf(
            "[FALLBACK] %s %s",
            $this->request->method(),
            $this->request->url()
        );
    }
}
