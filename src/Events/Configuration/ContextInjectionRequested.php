<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class ContextInjectionRequested extends Event
{
    public function __construct(
        public string $name,
        public object $instance,
        public array $context
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'name' => $this->name,
            'instance' => $this->instance,
            'context' => $this->context,
        ]);
    }
}