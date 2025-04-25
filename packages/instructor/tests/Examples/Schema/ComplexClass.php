<?php

namespace Cognesy\Instructor\Tests\Examples\Schema;

class ComplexClass
{
    /** is a bool var */
    public bool $boolVarWithDescription;
    public int $integerVar;
    public float $floatVar;
    /** information about string */
    public string $stringVarWithDescription;
    public IntEnum $integerEnumVar;
    public ?IntEnum $optionalIntegerEnumVar;
    public StringEnum $stringEnumVar;
    public ?StringEnum $optionalStringEnumVar;
    /** description of $simpleObject property */
    public SimpleClass $simpleObject;
    public ?SimpleClass $optionalSimpleObject;
    /** @var string[] */
    public array $arrayOfStrings;
    /** @var int[] */
    public array $arrayOfInts;
    /** @var float[] */
    public array $arrayOfFloats;
    /** @var bool[] */
    public array $arrayOfBools;
    /** @var StringEnum[] */
    public array $arrayOfStringEnums;
    /** @var IntEnum[] */
    public array $arrayOfIntEnums;
    /** @var SimpleClass[] description of array of SimpleClass */
    public array $arrayOfSimpleObjects;
}

