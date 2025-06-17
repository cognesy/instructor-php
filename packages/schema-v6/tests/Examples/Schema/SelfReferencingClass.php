<?php

namespace Cognesy\Schema\Tests\Examples\Schema;

class SelfReferencingClass
{
    public SelfReferencingClass $parent;
    public string $name;
}