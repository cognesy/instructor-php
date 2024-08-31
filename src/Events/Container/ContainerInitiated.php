<?php
namespace Cognesy\Instructor\Events\Container;

use Cognesy\Instructor\Events\Event;
use Psr\Log\LogLevel;

class ContainerInitiated extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct() {
        parent::__construct();
    }

    public function __toString() : string {
        return 'initated';
    }
}