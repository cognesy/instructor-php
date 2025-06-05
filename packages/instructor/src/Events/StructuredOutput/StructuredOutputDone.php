<?php

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

final class StructuredOutputDone extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        mixed $data
    ) {
        parent::__construct($data);
    }

    public function __toString(): string {
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'data' => $this->data,
        ];
    }
}
