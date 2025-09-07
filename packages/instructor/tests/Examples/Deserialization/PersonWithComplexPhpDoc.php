<?php

namespace Cognesy\Instructor\Tests\Examples\Deserialization;

class PersonWithComplexPhpDoc
{
    /** @var string The person's full name */
    public $name;
    
    /** @var int|null The person's age, can be null if unknown */
    public $age;
    
    /** @var Address[] List of addresses */
    public $addresses;
    
    /** @var array<string, mixed> Additional metadata */
    public $metadata;
}