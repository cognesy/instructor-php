<?php

namespace Cognesy\Schema\Tests\Examples\ClassInfo;

use Cognesy\Schema\Attributes\Description;

/**
 * Class description
 */
class TestClassA
{
    /** Property description */
    public mixed $mixedProperty = null;
    #[Description('Attribute description')]
    public mixed $attributeMixedProperty = null;
    public int $nonNullableIntProperty = 0;
    public mixed $explicitMixedProperty = null;
    public ?int $nullableIntProperty = null;
    public readonly string $readOnlyStringProperty;

    public function __construct(string $readOnlyStringProperty)
    {
        $this->readOnlyStringProperty = $readOnlyStringProperty;
    }
}