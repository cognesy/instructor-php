<?php

namespace Tests\Examples\Configuration;

class CircularDependencyB
{
    public CircularDependencyA $a;

    public function __construct(CircularDependencyA $a)
    {
        $this->a = $a;
    }
}