<?php

namespace Tests\Examples\Container;

// Test classes
class TestComponentA
{
    public $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }
}

