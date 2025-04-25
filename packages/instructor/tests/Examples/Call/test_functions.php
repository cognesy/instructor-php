<?php

namespace Cognesy\Instructor\Tests\Examples\Call;

/**
 * Test function description
 *
 * @param int $int int parameter
 * @param string $string string parameter
 * @param bool $bool bool parameter
 * @param TestClass $class object parameter
 *
 * @return string
 */
function testFunction(int $intParam, string $stringParam, bool $boolParam, TestClass $objectParam): string {
    return 'test';
}

function variadicFunction(TestClass ...$objectParams): string {
    return 'test';
}

function testFunctionWithDefault(?TestClass $objectParam = null, int $intParam = 1, string $stringParam = 'default', bool $boolParam = true): string {
    return 'test';
}
