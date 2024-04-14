<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ContextInjected extends Event
{
    public function __construct(
        public object $instance,
        public array $context
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'context' => $this->context,
            'instance' => $this->instance
        ]);
    }
}