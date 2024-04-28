<?php

namespace Cognesy\Instructor\Traits;

trait HasFacade
{
    static private $instance;

    static public function instance() : static {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    static public function __callStatic(string $method, array $args) {
        return self::instance()->$method(...$args);
    }
}