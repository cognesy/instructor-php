<?php

namespace Tests\Examples\Container;

class CircularDependencyA
{
    public $b;

    public function __construct(CircularDependencyB $b)
    {
        $this->b = $b;
    }
}
