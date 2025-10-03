<?php

namespace Cognesy\Schema\Tests\Examples\Schema;

class SelfReferencingClass
{
    public SelfReferencingClass $parent;
    public string $name = '';

    public function __construct(?SelfReferencingClass $parent = null)
    {
        $this->parent = $parent ?? new SelfReferencingClass();
    }
}
