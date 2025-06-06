<?php

namespace Cognesy\Http\Events;

class DebugStreamLineReceived extends DebugEvent
{
    public function __construct(
        public string $line,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'line' => $this->line,
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}