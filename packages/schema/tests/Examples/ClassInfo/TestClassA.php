<?php

namespace Cognesy\Schema\Tests\Examples\ClassInfo;

use Cognesy\Schema\Attributes\Description;

/**
 * Class description
 */
class TestClassA
{
    /** Property description */
    public $mixedProperty;
    #[Description('Attribute description')]
    public $attributeMixedProperty;
    public int $nonNullableIntProperty;
    public mixed $explicitMixedProperty;
    public ?int $nullableIntProperty;
    public readonly string $readOnlyStringProperty;
}