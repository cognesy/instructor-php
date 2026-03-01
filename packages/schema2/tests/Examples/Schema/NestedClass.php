<?php

namespace Cognesy\Schema\Tests\Examples\Schema;

class NestedClass
{
    public int $nestedIntVar = 0;
    public float $nestedFloatVar = 0.0;
    public string $nestedStringVar = '';
    public StringEnum $nestedStringEnumVar = StringEnum::CaseA;
    /** @var string[] */
    public array $nestedArrayOfStrings = [];
    /** @var bool[] */
    public array $nestedArrayOfBools = [];
    /** @var int[] */
    public array $nestedArrayOfIntegers = [];
    /** @var IntEnum[] */
    public array $nestedArrayOfIntegerEnums = [];
}
