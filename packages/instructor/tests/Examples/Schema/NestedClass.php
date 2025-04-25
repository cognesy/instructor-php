<?php

namespace Cognesy\Instructor\Tests\Examples\Schema;

class NestedClass
{
    public int $nestedIntVar;
    public float $nestedFloatVar;
    public string $nestedStringVar;
    public StringEnum $nestedStringEnumVar;
    /** @var string[] */
    public array $nestedArrayOfStrings;
    /** @var bool[] */
    public array $nestedArrayOfBools;
    /** @var int[] */
    public array $nestedArrayOfIntegers;
    /** @var IntEnum[] */
    public array $nestedArrayOfIntegerEnums;
}
