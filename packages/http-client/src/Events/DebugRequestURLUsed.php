<?php

namespace Cognesy\Http\Events;

class DebugRequestURLUsed extends DebugEvent
{
    public function __construct(public string $url) {
        parent::__construct();
    }

    public function toArray(): array {
        return ['url' => $this->url];
    }

    public function __toString(): string {
        return $this->url;
    }
}