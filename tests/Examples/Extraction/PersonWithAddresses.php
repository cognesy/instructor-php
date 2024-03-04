<?php

namespace Tests\Examples\Extraction;

class PersonWithAddresses
{
    public string $name;
    public ?int $age;
    /** @var \Tests\Examples\Extraction\Address[] */
    public array $addresses;
}
