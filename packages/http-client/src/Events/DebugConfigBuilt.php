<?php

namespace Cognesy\Http\Events;

use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Utils\Events\Event;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
final class DebugConfigBuilt extends Event
{
    public function __construct(
        public DebugConfig $debugConfig,
    ) {
        parent::__construct();
    }

    public function toArray(): array {
        return [
            'debug' => $this->debugConfig->toArray(),
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}