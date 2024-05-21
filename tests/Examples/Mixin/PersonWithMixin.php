<?php

namespace Tests\Examples\Mixin;

use Cognesy\Instructor\Extras\Mixin\HandlesSelfExtraction;

class PersonWithMixin
{
    use HandlesSelfExtraction;

    public string $name;
    public int $age;
}
