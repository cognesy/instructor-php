<?php

namespace Cognesy\Http\Events;

class DebugRequestHeadersUsed extends DebugEvent
{
    public function __construct(
        public array $headers,
    ) {
        parent::__construct();
    }

    public function toArray(): array {
        return [
            'headers' => $this->headers,
        ];
    }

    public function __toString(): string {
        return json_encode($this->headers);
    }
}