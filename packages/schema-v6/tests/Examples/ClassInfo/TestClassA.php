<?php

namespace Cognesy\Schema\Tests\Examples\ClassInfo;

use Cognesy\Schema\Attributes\Description;

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