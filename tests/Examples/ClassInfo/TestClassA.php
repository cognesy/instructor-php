<?php

namespace Tests\Examples\ClassInfo;

use Cognesy\Instructor\Features\Schema\Attributes\Description;

/**
 * Class description
 */
class TestClassA
{
    /** Property description */
    public $testProperty;
    #[Description('Attribute description')]
    public $attributeProperty;
    public int $nonNullableProperty;
    public mixed $publicProperty;
    public ?int $nullableProperty;
    public readonly string $readOnlyProperty;
}