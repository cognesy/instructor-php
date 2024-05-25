<?php

namespace Cognesy\Instructor\Configuration\Traits;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;
use function Cognesy\config\autowire;

trait HasConfigurationInstance
{
    private static ?Configuration $instance = null;

    /**
     * Get the singleton of empty configuration
     */
    static public function instance() : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = new Configuration();
        }
        return self::$instance;
    }

    /**
     * Get singleton of autowired configuration
     */
    static public function auto(array $overrides = [], EventDispatcher $events = null) : Configuration {
        if (is_null(self::$instance)) {
            $events = $events ?? new EventDispatcher();
            self::$instance = autowire(new Configuration, $events)->override($overrides);
        }
        return self::$instance;
    }
}