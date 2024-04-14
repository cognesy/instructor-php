<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Throwable;

class ComponentCreationFailed extends Event
{
    public function __construct(
        public string $name,
        public Throwable $error,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'name' => $this->name,
            'error' => $this->error->getMessage(),
            'trace' => $this->error->getTraceAsString()
        ]);
    }
}