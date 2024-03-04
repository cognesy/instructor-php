<?php

namespace Tests\Examples\Schema;

class SelfReferencingClass
{
    public SelfReferencingClass $parent;
    public string $name;
}