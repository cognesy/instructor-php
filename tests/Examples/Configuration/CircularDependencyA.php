<?php

namespace Tests\Examples\Configuration;

class CircularDependencyA
{
    public $b;

    public function __construct(CircularDependencyB $b)
    {
        $this->b = $b;
    }
}
