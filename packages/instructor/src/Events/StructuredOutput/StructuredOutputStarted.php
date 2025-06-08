<?php

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Events\Event;
use Psr\Log\LogLevel;

final class StructuredOutputStarted extends Event
{
    public $logLevel = LogLevel::INFO;

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