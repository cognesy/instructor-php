<?php

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Instructor\Tests\Examples\Structure\TestEnum;
use Cognesy\Instructor\Tests\Examples\Structure\TestNestedObject;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;

function testFunctionWithParams(
    string $stringParam,
    int $intParam,
    ?bool $boolParam = false,
    array $arrayParam = [],
    TestEnum $enumParam = TestEnum::A
): void {}

class TestClassForFactory {
    public static function staticMethod(string $param1, int $param2): void {}

    public function instanceMethod(string $param1, ?float $param2 = null): string {
        return '';
    }

    public function methodWithVariadic(string $param1, string ...$items): void {}
}

it('creates structure from function name', function() {
    $structure = StructureFactory::fromFunctionName('testFunctionWithParams');

    expect($structure->name())->toBe('testFunctionWithParams');
    expect($structure->fields())->toHaveCount(5);

    $stringField = $structure->field('stringParam');
    expect($stringField->name())->toBe('stringParam');
    expect($stringField->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);
    expect($stringField->isOptional())->toBeFalse();

    $intField = $structure->field('intParam');
    expect($intField->name())->toBe('intParam');
    expect($intField->typeDetails()->type())->toBe(TypeDetails::PHP_INT);
    expect($intField->isOptional())->toBeFalse();

    $boolField = $structure->field('boolParam');
    expect($boolField->name())->toBe('boolParam');
    expect($boolField->typeDetails()->type())->toBe(TypeDetails::PHP_BOOL);
    expect($boolField->isOptional())->toBeTrue();
    expect($boolField->defaultValue())->toBe(false);

    $arrayField = $structure->field('arrayParam');
    expect($arrayField->name())->toBe('arrayParam');
    expect($arrayField->typeDetails()->type())->toBe(TypeDetails::PHP_ARRAY);
    expect($arrayField->isOptional())->toBeTrue();
    expect($arrayField->defaultValue())->toBe([]);

    $enumField = $structure->field('enumParam');
    expect($enumField->name())->toBe('enumParam');
    expect($enumField->typeDetails()->class)->toBe(TestEnum::class);
    expect($enumField->isOptional())->toBeTrue();
    expect($enumField->defaultValue())->toBe(TestEnum::A);
});

it('creates structure from function name with custom name and description', function() {
    $structure = StructureFactory::fromFunctionName(
        'testFunctionWithParams',
        'CustomName',
        'Custom description'
    );

    expect($structure->name())->toBe('CustomName');
    expect($structure->description())->toBe('Custom description');
});

it('creates structure from static method name', function() {
    $structure = StructureFactory::fromMethodName(
        TestClassForFactory::class,
        'staticMethod'
    );

    expect($structure->name())->toBe('staticMethod');
    expect($structure->fields())->toHaveCount(2);

    $param1 = $structure->field('param1');
    expect($param1->name())->toBe('param1');
    expect($param1->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);

    $param2 = $structure->field('param2');
    expect($param2->name())->toBe('param2');
    expect($param2->typeDetails()->type())->toBe(TypeDetails::PHP_INT);
});

it('creates structure from instance method name', function() {
    $structure = StructureFactory::fromMethodName(
        TestClassForFactory::class,
        'instanceMethod'
    );

    expect($structure->name())->toBe('instanceMethod');
    expect($structure->fields())->toHaveCount(2);

    $param1 = $structure->field('param1');
    expect($param1->name())->toBe('param1');
    expect($param1->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);
    expect($param1->isOptional())->toBeFalse();

    $param2 = $structure->field('param2');
    expect($param2->name())->toBe('param2');
    expect($param2->typeDetails()->type())->toBe(TypeDetails::PHP_FLOAT);
    expect($param2->isOptional())->toBeTrue();
    expect($param2->defaultValue())->toBeNull();
});

it('creates structure from method with variadic parameters', function() {
    $structure = StructureFactory::fromMethodName(
        TestClassForFactory::class,
        'methodWithVariadic'
    );

    expect($structure->name())->toBe('methodWithVariadic');
    expect($structure->fields())->toHaveCount(2);

    $param1 = $structure->field('param1');
    expect($param1->name())->toBe('param1');
    expect($param1->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);

    $items = $structure->field('items');
    expect($items->name())->toBe('items');
    expect($items->typeDetails()->type())->toBe(TypeDetails::PHP_COLLECTION);
});

it('creates structure from callable closure', function() {
    $callable = 'testFunctionWithParams';

    $structure = StructureFactory::fromCallable($callable);

    expect($structure->fields())->toHaveCount(5);

    $stringField = $structure->field('stringParam');
    expect($stringField->name())->toBe('stringParam');
    expect($stringField->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);

    $intField = $structure->field('intParam');
    expect($intField->name())->toBe('intParam');
    expect($intField->typeDetails()->type())->toBe(TypeDetails::PHP_INT);

    $boolField = $structure->field('boolParam');
    expect($boolField->name())->toBe('boolParam');
    expect($boolField->typeDetails()->type())->toBe(TypeDetails::PHP_BOOL);
    expect($boolField->isOptional())->toBeTrue();
});

it('creates structure from callable array', function() {
    $testClass = new TestClassForFactory();
    $callable = [$testClass, 'instanceMethod'];

    $structure = StructureFactory::fromCallable($callable, 'CustomCallableName');

    expect($structure->name())->toBe('CustomCallableName');
    expect($structure->fields())->toHaveCount(2);

    $param1 = $structure->field('param1');
    expect($param1->name())->toBe('param1');
    expect($param1->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);

    $param2 = $structure->field('param2');
    expect($param2->name())->toBe('param2');
    expect($param2->typeDetails()->type())->toBe(TypeDetails::PHP_FLOAT);
    expect($param2->isOptional())->toBeTrue();
});

it('creates structure from Schema object', function() {
    $schema = Schema::object(
        class: TestNestedObject::class,
        name: 'TestSchema',
        description: 'Test schema description',
        properties: [
            'stringProp' => Schema::string('stringProp', 'String property'),
            'intProp' => Schema::int('intProp', 'Integer property'),
            'boolProp' => Schema::bool('boolProp', 'Boolean property'),
            'floatProp' => Schema::float('floatProp', 'Float property'),
        ],
        required: ['stringProp', 'intProp']
    );

    $structure = StructureFactory::fromSchema('CustomSchemaName', $schema);

    expect($structure->name())->toBe('CustomSchemaName');
    expect($structure->description())->toBe('Test schema description');
    expect($structure->fields())->toHaveCount(4);

    $stringProp = $structure->field('stringProp');
    expect($stringProp->name())->toBe('stringProp');
    expect($stringProp->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);
    expect($stringProp->isOptional())->toBeFalse();

    $intProp = $structure->field('intProp');
    expect($intProp->name())->toBe('intProp');
    expect($intProp->typeDetails()->type())->toBe(TypeDetails::PHP_INT);
    expect($intProp->isOptional())->toBeFalse();

    $boolProp = $structure->field('boolProp');
    expect($boolProp->name())->toBe('boolProp');
    expect($boolProp->typeDetails()->type())->toBe(TypeDetails::PHP_BOOL);
    expect($boolProp->isOptional())->toBeTrue();

    $floatProp = $structure->field('floatProp');
    expect($floatProp->name())->toBe('floatProp');
    expect($floatProp->typeDetails()->type())->toBe(TypeDetails::PHP_FLOAT);
    expect($floatProp->isOptional())->toBeTrue();
});

it('creates structure from string with simple format', function() {
    $structure = StructureFactory::fromString(
        'UserData',
        'name:string, age:int, active:bool',
        'User data structure'
    );

    expect($structure->name())->toBe('UserData');
    expect($structure->description())->toBe('User data structure');
    expect($structure->fields())->toHaveCount(3);

    $nameField = $structure->field('name');
    expect($nameField->name())->toBe('name');
    expect($nameField->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);

    $ageField = $structure->field('age');
    expect($ageField->name())->toBe('age');
    expect($ageField->typeDetails()->type())->toBe(TypeDetails::PHP_INT);

    $activeField = $structure->field('active');
    expect($activeField->name())->toBe('active');
    expect($activeField->typeDetails()->type())->toBe(TypeDetails::PHP_BOOL);
});

it('creates structure from string with array format', function() {
    $structure = StructureFactory::fromString(
        'UserData',
        'array{name: string, age: int, score: float}',
        'User data'
    );

    expect($structure->name())->toBe('UserData');
    expect($structure->fields())->toHaveCount(3);

    $nameField = $structure->field('name');
    expect($nameField->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);

    $ageField = $structure->field('age');
    expect($ageField->typeDetails()->type())->toBe(TypeDetails::PHP_INT);

    $scoreField = $structure->field('score');
    expect($scoreField->typeDetails()->type())->toBe(TypeDetails::PHP_FLOAT);
});

it('creates structure from string with descriptions', function() {
    $structure = StructureFactory::fromString(
        'UserData',
        'name:string (User full name), age:int (User age in years), active:bool (Is user active)',
        'User data with descriptions'
    );

    expect($structure->name())->toBe('UserData');
    expect($structure->fields())->toHaveCount(3);

    $nameField = $structure->field('name');
    expect($nameField->name())->toBe('name');
    expect($nameField->description())->toBe('User full name');

    $ageField = $structure->field('age');
    expect($ageField->name())->toBe('age');
    expect($ageField->description())->toBe('User age in years');

    $activeField = $structure->field('active');
    expect($activeField->name())->toBe('active');
    expect($activeField->description())->toBe('Is user active');
});

it('creates structure from string with default types', function() {
    $structure = StructureFactory::fromString(
        'SimpleData',
        'field1, field2, field3',
        'Simple data'
    );

    expect($structure->fields())->toHaveCount(3);

    // When no type is specified, it defaults to string
    expect($structure->field('field1')->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);
    expect($structure->field('field2')->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);
    expect($structure->field('field3')->typeDetails()->type())->toBe(TypeDetails::PHP_STRING);
});

it('throws exception for invalid string format', function() {
    StructureFactory::fromString(
        'InvalidData',
        'field1:string:extra:parts',
        'Invalid format'
    );
})->throws(InvalidArgumentException::class);