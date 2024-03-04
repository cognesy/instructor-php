<?php

namespace Tests\Examples\Schema;

class NestedClass
{
    public string $nestedStringVar;
    public StringEnum $nestedStringEnumVar;
    /** @var string[] */
    public array $nestedArrayOfStrings;
    /** @var IntEnum[] */
    public array $nestedArrayOfIntegerEnums;
}
