<?php

namespace Cognesy\Instructor\Container\Traits;

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Events\EventDispatcher;

trait HasConfigurationInstance
{
    private static ?Container $instance = null;

    /**
     * Get the singleton of empty configuration
     */
    static public function instance(EventDispatcher $events = null) : Container {
        if (is_null(self::$instance)) {
            self::$instance = new Container($events);
        }
        return self::$instance;
    }

    /**
     * Always new, autowired configuration; useful mostly for tests
     */
    static public function fresh(EventDispatcher $events = null) : Container {
        return new Container($events);
    }
}