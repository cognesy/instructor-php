<?php

namespace Tests\Examples\Mixin;

use Cognesy\Instructor\Extras\Mixin\HandlesExtraction;

class PersonWithMixin
{
    use HandlesExtraction;

    public string $name;
    public int $age;
}
