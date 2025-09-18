<?php

namespace Cognesy\Instructor\Tests\Examples\Deserialization;

class PersonWithPhpDocTypes
{
    /** @var string */
    public $name;
    
    /** @var int */
    public $age;
    
    /** @var string[] */
    public $hobbies;
    
    /** @var Address */
    public $address;
}