<?php

namespace Cognesy\Http\Events;

class DebugResponseBodyReceived extends DebugEvent
{
    public function __construct(public string $body,) {
        parent::__construct();
    }

    public function toArray(): array {
        return ['body' => $this->body];
    }

    public function __toString(): string {
        return $this->body;
    }
}