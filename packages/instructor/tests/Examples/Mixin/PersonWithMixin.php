<?php

namespace Cognesy\Instructor\Tests\Examples\Mixin;

use Cognesy\Instructor\Extras\Mixin\HandlesSelfInference;

class PersonWithMixin
{
    use HandlesSelfInference;

    public string $name;
    public int $age;
}
