<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;
use Throwable;

class ComponentCreationFailed extends Event
{
    public $logLevel = LogLevel::CRITICAL;

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