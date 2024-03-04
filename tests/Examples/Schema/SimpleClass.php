<?php

namespace Tests\Examples\Schema;

class SimpleClass
{
    public string $stringVar;
    public StringEnum $stringEnumVar;
    /** @var string[] */
    public array $arrayOfStrings;
    /** @var IntEnum[] */
    public array $arrayOfIntegerEnums;
    /** this tests double nested classes resolution */
    public NestedClass $nestedClassVar;
    /** this tests self-referencing classes resolution */
    public SelfReferencingClass $selfReferencingClassVar;
}
