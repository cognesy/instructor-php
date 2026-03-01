<?php

namespace Cognesy\Schema\Tests\Examples\Schema;

class ComplexClass
{
    /** is a bool var */
    public bool $boolVarWithDescription = false;
    public int $integerVar = 0;
    public float $floatVar = 0.0;
    /** information about string */
    public string $stringVarWithDescription = '';
    public IntEnum $integerEnumVar = IntEnum::Case1;
    public ?IntEnum $optionalIntegerEnumVar = null;
    public StringEnum $stringEnumVar = StringEnum::CaseA;
    public ?StringEnum $optionalStringEnumVar = null;
    /** description of $simpleObject property */
    public SimpleClass $simpleObject;
    public ?SimpleClass $optionalSimpleObject = null;
    /** @var string[] */
    public array $arrayOfStrings = [];
    /** @var int[] */
    public array $arrayOfInts = [];
    /** @var float[] */
    public array $arrayOfFloats = [];
    /** @var bool[] */
    public array $arrayOfBools = [];
    /** @var StringEnum[] */
    public array $arrayOfStringEnums = [];
    /** @var IntEnum[] */
    public array $arrayOfIntEnums = [];
    /** @var SimpleClass[] description of array of SimpleClass */
    public array $arrayOfSimpleObjects = [];

    public function __construct(SimpleClass $simpleObject)
    {
        $this->simpleObject = $simpleObject;
    }
}

