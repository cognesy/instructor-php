<?php

namespace Tests\Examples\Container;

class CircularDependencyB
{
    public CircularDependencyA $a;

    public function __construct(CircularDependencyA $a)
    {
        $this->a = $a;
    }
}