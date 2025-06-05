<?php

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Utils\Events\Event;

final class StructuredOutputReady extends Event
{
    public function __construct() {
        parent::__construct();
    }

    public function __toString(): string {
        return '';
    }

    public function toArray(): array {
        return [];
    }
}
