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

    /**
     * Always new, autowired configuration; useful mostly for tests
     */
    static public function fresh(EventDispatcher $events = null) : Configuration {
        return new Configuration($events);
    }
}