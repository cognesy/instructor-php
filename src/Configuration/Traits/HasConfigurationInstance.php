<?php

namespace Cognesy\Instructor\Configuration\Traits;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;

trait HasConfigurationInstance
{
    private static ?Configuration $instance = null;

    /**
     * Get the singleton of empty configuration
     */
    static public function instance(EventDispatcher $events = null) : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = new Configuration($events);
        }
        return self::$instance;
    }
}