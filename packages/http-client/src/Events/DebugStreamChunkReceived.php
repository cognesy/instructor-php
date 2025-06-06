<?php

namespace Cognesy\Http\Events;

class DebugStreamChunkReceived extends DebugEvent
{
    public function __construct(
        public string $chunk,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'chunk' => $this->chunk,
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}