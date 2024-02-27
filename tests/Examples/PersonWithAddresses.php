<?php

namespace Tests\Examples;

class PersonWithAddresses
{
    public string $name;
    public ?int $age;
    /** @var \Tests\Examples\Address[] */
    public array $addresses;
}
