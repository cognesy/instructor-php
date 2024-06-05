<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ConfigurationReady extends Event
{
    public function __construct(
        public array $configuration
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->configuration);
    }
}