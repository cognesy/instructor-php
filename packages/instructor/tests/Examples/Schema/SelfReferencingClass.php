<?php

namespace Cognesy\Instructor\Tests\Examples\Schema;

class SelfReferencingClass
{
    public SelfReferencingClass $parent;
    public string $name;
}