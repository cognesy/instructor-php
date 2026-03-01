<?php
namespace Cognesy\Schema\Tests\Examples\SchemaConverter;

class TestNestedObject {
    public string $nestedStringProperty = '';
    public TestObject $nestedObjectProperty;

    public function __construct(?TestObject $nestedObjectProperty = null)
    {
        $this->nestedObjectProperty = $nestedObjectProperty ?? new TestObject();
    }
}
