<?php

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Events\Event;
use Psr\Log\LogLevel;

final class RequestReceived extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return 'OK';
    }

    public function toArray(): array {
        return [];
    }
}
