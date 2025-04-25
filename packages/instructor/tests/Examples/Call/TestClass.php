<?php

namespace Cognesy\Instructor\Tests\Examples\Call;

class TestClass {
    public int $intField;
    public string $stringField;
    public bool $boolField;

    static public function make(int $intField, string $stringField, bool $boolField): static {
        $instance = new static();
        $instance->intField = $intField;
        $instance->stringField = $stringField;
        $instance->boolField = $boolField;
        return $instance;
    }

    /**
     * Test method description
     *
     * @param int $intParam int parameter
     * @param string $stringParam string parameter
     * @param bool $boolParam bool parameter
     * @param TestClass $classParam object parameter
     *
     * @return string
     */
    public function testMethod(int $intParam, string $stringParam, bool $boolParam, TestClass $objectParam): string {
        return 'test';
    }
}
