<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ReferenceResolutionRequested extends Event
{
    public function __construct(
        public string $name,
        public bool $fresh,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'name' => $this->name,
            'fresh' => $this->fresh,
        ]);
    }
}
