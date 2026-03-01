<?php

namespace Cognesy\Schema\Tests\Examples\Schema;

class SimpleClass
{
    public string $stringVar = '';
    public StringEnum $stringEnumVar = StringEnum::CaseA;
    /** @var string[] */
    public array $arrayOfStrings = [];
    /** @var IntEnum[] */
    public array $arrayOfIntegerEnums = [];
    /** this tests double nested classes resolution */
    public NestedClass $nestedClassVar;
    /** this tests self-referencing classes resolution */
    public SelfReferencingClass $selfReferencingClassVar;

    public function __construct(?NestedClass $nestedClassVar = null, ?SelfReferencingClass $selfReferencingClassVar = null)
    {
        $this->nestedClassVar = $nestedClassVar ?? new NestedClass();
        $this->selfReferencingClassVar = $selfReferencingClassVar ?? new SelfReferencingClass();
    }
}
