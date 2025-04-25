<?php
namespace Cognesy\Instructor\Tests\Examples\Extraction;

use Symfony\Component\Validator\Constraints as Assert;

class Person {
    #[Assert\Length(min: 3)]
    public string $name;
    #[Assert\PositiveOrZero]
    public int $age;
}
