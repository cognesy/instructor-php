<?php

namespace Cognesy\Http\Events;

class DebugResponseHeadersReceived extends DebugEvent
{
    public function __construct(public array $headers) {
        parent::__construct();
    }

    public function toArray(): array {
        return $this->headers;
    }

    public function __toString(): string {
        return json_encode($this->headers);
    }
}