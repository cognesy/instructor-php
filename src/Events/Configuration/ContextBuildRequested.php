<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class ContextBuildRequested extends Event
{
    public function __construct(
        public string $name,
        public array $context
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'name' => $this->name,
            'context' => $this->context
        ]);
    }
}