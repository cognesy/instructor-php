<?php

namespace Cognesy\Instructor\Tests\Examples\Extraction;

class PersonWithAddresses
{
    public string $name;
    public ?int $age;
    /** @var Address[] */
    public array $addresses;
}
