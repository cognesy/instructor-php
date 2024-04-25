<?php

namespace Cognesy\Instructor\Configuration\Traits;

use Cognesy\Instructor\Configuration\Configuration;
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
    static public function auto(array $overrides = []) : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = autowire(new Configuration)->override($overrides);
        }
        return self::$instance;
    }
}