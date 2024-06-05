<?php

namespace Cognesy\Instructor\Events\Configuration;

use Cognesy\Instructor\Events\Event;
use Psr\Log\LogLevel;

class ConfigurationInitiated extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct() {
        parent::__construct();
    }

    public function __toString() : string {
        return 'initated';
    }
}