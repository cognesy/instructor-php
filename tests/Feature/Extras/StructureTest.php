<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use DateTime;

enum TestEnum : string {
    case A = 'A';
    case B = 'B';
}

class TestNestedObject {
    public string $stringProperty;
    public int $integerProperty;
    public bool $boolProperty;
    public float $floatProperty;
    public DateTime $datetimeProperty;
    public TestEnum $enumProperty;
    /** @var string[] */
    public array $arrayProperty;
}

it('creates structure', function () {
    $structure = Structure::define('TestStructure', [
        Field::string('stringProperty', 'String property'),
        Field::int('integerProperty', 'Integer property'),
        Field::bool('boolProperty', 'Boolean property'),
        Field::float('floatProperty', 'Float property'),
        Field::datetime('datetimeProperty', 'Datetime property'),
        Field::enum('enumProperty', TestEnum::class, 'Enum property'),
        Field::object('objectProperty', TestNestedObject::class, 'Object property'),
        Field::array('arrayProperty', TypeDetails::PHP_STRING, 'Array property'),
        Field::array('arrayObjectProperty', TestNestedObject::class, 'Array object property'),
        Field::array('arrayEnumProperty', TestEnum::class, 'Array enum property'),
    ]);

    expect($structure->fields())->toHaveCount(10);
    expect($structure->field('stringProperty')->name())->toBe('stringProperty');
    expect($structure->field('integerProperty')->name())->toBe('integerProperty');
    expect($structure->field('boolProperty')->name())->toBe('boolProperty');
    expect($structure->field('floatProperty')->name())->toBe('floatProperty');
    expect($structure->field('datetimeProperty')->name())->toBe('datetimeProperty');
    expect($structure->field('enumProperty')->name())->toBe('enumProperty');
    expect($structure->field('objectProperty')->name())->toBe('objectProperty');
    expect($structure->field('arrayProperty')->name())->toBe('arrayProperty');
    expect($structure->field('arrayObjectProperty')->name())->toBe('arrayObjectProperty');
    expect($structure->field('arrayEnumProperty')->name())->toBe('arrayEnumProperty');
});

it('serializes structure', function() {
    $structure = Structure::define('TestStructure', [
        Field::string('stringProperty', 'String property'),
        Field::int('integerProperty', 'Integer property'),
        Field::bool('boolProperty', 'Boolean property'),
        Field::float('floatProperty', 'Float property'),
        Field::datetime('datetimeProperty', 'Datetime property'),
        Field::enum('enumProperty', TestEnum::class, 'Enum property'),
        Field::object('objectProperty', TestNestedObject::class, 'Object property'),
        Field::array('arrayProperty', TypeDetails::PHP_STRING, 'Array property'),
        Field::array('arrayObjectProperty', TestNestedObject::class, 'Array object property'),
        Field::array('arrayEnumProperty', TestEnum::class, 'Array enum property'),
    ]);

    $structure->stringProperty = 'string';
    $structure->integerProperty = 1;
    $structure->boolProperty = true;
    $structure->floatProperty = 1.1;
    $structure->datetimeProperty = new DateTime('2020-10-01');
    $structure->enumProperty = TestEnum::A;
    $structure->objectProperty = new TestNestedObject();
    $structure->arrayProperty = ['a', 'b', 'c'];
    $structure->arrayObjectProperty = [new TestNestedObject(), new TestNestedObject()];
    $structure->arrayEnumProperty = [TestEnum::A, TestEnum::B];

    $data = $structure->toArray();
    expect($data)->toHaveCount(10);
    expect($data['stringProperty'])->toBe('string');
    expect($data['integerProperty'])->toBe(1);
    expect($data['boolProperty'])->toBe(true);
    expect($data['floatProperty'])->toBe(1.1);
    expect($data['datetimeProperty'])->toBe('2020-10-01 00:00:00');
    expect($data['enumProperty'])->toBe(TestEnum::A->value);
    expect($data['objectProperty'])->toBe([]);
    expect($data['arrayProperty'])->toBe(['a', 'b', 'c']);
    expect($data['arrayObjectProperty'])->toBeArray();
    expect($data['arrayObjectProperty'])->toHaveCount(2);
    expect($data['arrayEnumProperty'])->toBe(['A', 'B']);
});

it('deserializes structure', function() {
    $structure = Structure::define('TestStructure', [
        Field::string('stringProperty', 'String property'),
        Field::int('integerProperty', 'Integer property'),
        Field::bool('boolProperty', 'Boolean property'),
        Field::float('floatProperty', 'Float property'),
        Field::enum('enumProperty', TestEnum::class, 'Enum property'),
        Field::structure('structureProperty', [
            Field::string('stringProperty', 'String property'),
            Field::int('integerProperty', 'Integer property'),
            Field::bool('boolProperty', 'Boolean property'),
        ], 'Object property'),
        Field::object('objectProperty', TestNestedObject::class, 'Object property')->optional(),
        Field::array('arrayProperty', TypeDetails::PHP_STRING, 'Array property'),
        Field::array('arrayDateProperty', DateTime::class, 'Array object property')->optional(),
        Field::array('arrayEnumProperty', TestEnum::class, 'Array enum property'),
    ]);

    $data = [
        'stringProperty' => 'string',
        'integerProperty' => 1,
        'boolProperty' => true,
        'floatProperty' => 1.1,
        'enumProperty' => TestEnum::A->value,
        'structureProperty' => [
            'stringProperty' => 'string',
            'integerProperty' => 1,
            'boolProperty' => true,
        ],
        'objectProperty' => [
            'stringProperty' => 'string',
            'integerProperty' => 1,
            'boolProperty' => true,
            'floatProperty' => 1.1,
            'datetimeProperty' => '2020-10-01',
            'enumProperty' => TestEnum::A->value,
            'arrayProperty' => ['a', 'b', 'c'],
        ],
        'arrayProperty' => ['a', 'b', 'c'],
        'arrayDateProperty' => ['2020-10-01', '2021-01-12'],
        'arrayEnumProperty' => ['A', 'B'],
    ];

    $structure->fromArray($data);

    expect($structure->stringProperty)->toBe('string');
    expect($structure->integerProperty)->toBe(1);
    expect($structure->boolProperty)->toBe(true);
    expect($structure->floatProperty)->toBe(1.1);
    expect($structure->enumProperty)->toBe(TestEnum::A);
    expect($structure->structureProperty)->toBeInstanceOf(Structure::class);
    expect($structure->structureProperty->stringProperty)->toBe('string');
    expect($structure->structureProperty->integerProperty)->toBe(1);
    expect($structure->structureProperty->boolProperty)->toBe(true);
    expect($structure->objectProperty)->toBeInstanceOf(TestNestedObject::class);
    expect($structure->objectProperty->stringProperty)->toBe('string');
    expect($structure->objectProperty->integerProperty)->toBe(1);
    expect($structure->objectProperty->boolProperty)->toBe(true);
    expect($structure->objectProperty->floatProperty)->toBe(1.1);
    expect($structure->objectProperty->datetimeProperty)->toBeInstanceOf(DateTime::class);
    expect($structure->objectProperty->datetimeProperty->format("Y-m-d"))->toBe('2020-10-01');
    expect($structure->objectProperty->enumProperty)->toBe(TestEnum::A);
    expect($structure->objectProperty->arrayProperty)->toBe(['a', 'b', 'c']);
    expect($structure->arrayProperty)->toBe(['a', 'b', 'c']);
    expect($structure->arrayDateProperty)->toBeArray();
    expect($structure->arrayDateProperty)->toHaveCount(2);
    expect($structure->arrayDateProperty[0])->toBeInstanceOf(DateTime::class);
    expect($structure->arrayDateProperty[1])->toBeInstanceOf(DateTime::class);
    expect($structure->arrayEnumProperty)->toBeArray();
    expect($structure->arrayEnumProperty)->toHaveCount(2);
    expect($structure->arrayEnumProperty[0])->toBe(TestEnum::A);
    expect($structure->arrayEnumProperty[1])->toBe(TestEnum::B);
});

it('creates structure from class', function() {
    $structure = Structure::fromClass(TestNestedObject::class);

    expect($structure->fields())->toHaveCount(7);
    expect($structure->field('stringProperty')->name())->toBe('stringProperty');
    expect($structure->field('integerProperty')->name())->toBe('integerProperty');
    expect($structure->field('boolProperty')->name())->toBe('boolProperty');
    expect($structure->field('floatProperty')->name())->toBe('floatProperty');
    expect($structure->field('datetimeProperty')->name())->toBe('datetimeProperty');
    expect($structure->field('enumProperty')->name())->toBe('enumProperty');
    expect($structure->field('arrayProperty')->name())->toBe('arrayProperty');
});

it('creates structure from JSON Schema', function() {
    $jsonSchema = [
        'title' => 'TestStructure',
        'description' => 'Test structure',
        'properties' => [
            'stringProperty' => [
                'type' => 'string',
                'description' => 'String property',
            ],
            'integerProperty' => [
                'type' => 'integer',
                'description' => 'Integer property',
            ],
            'boolProperty' => [
                'type' => 'boolean',
                'description' => 'Boolean property',
            ],
            'floatProperty' => [
                'type' => 'number',
                'description' => 'Float property',
            ],
            'enumProperty' => [
                'type' => 'string',
                'description' => 'Enum property',
            ],
        ],
    ];

    $structure = Structure::fromJsonSchema($jsonSchema);

    expect($structure->fields())->toHaveCount(5);
    expect($structure->field('integerProperty')->name())->toBe('integerProperty');
    expect($structure->field('integerProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_INT);
    expect($structure->field('stringProperty')->name())->toBe('stringProperty');
    expect($structure->field('stringProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_STRING);
    expect($structure->field('boolProperty')->name())->toBe('boolProperty');
    expect($structure->field('boolProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_BOOL);
    expect($structure->field('floatProperty')->name())->toBe('floatProperty');
    expect($structure->field('floatProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_FLOAT);
    expect($structure->field('enumProperty')->name())->toBe('enumProperty');
    expect($structure->field('enumProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_STRING);
});

it('creates structure from array', function() {
    $structure = Structure::fromArrayKeyValues('TestStructure', [
        'stringProperty' => 'string',
        'integerProperty' => 1,
        'boolProperty' => true,
        'floatProperty' => 1.1,
        'enumProperty' => TestEnum::A->value,
    ]);

    expect($structure->fields())->toHaveCount(5);
    expect($structure->field('integerProperty')->name())->toBe('integerProperty');
    expect($structure->field('integerProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_INT);
    expect($structure->field('stringProperty')->name())->toBe('stringProperty');
    expect($structure->field('stringProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_STRING);
    expect($structure->field('boolProperty')->name())->toBe('boolProperty');
    expect($structure->field('boolProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_BOOL);
    expect($structure->field('floatProperty')->name())->toBe('floatProperty');
    expect($structure->field('floatProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_FLOAT);
    expect($structure->field('enumProperty')->name())->toBe('enumProperty');
    expect($structure->field('enumProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_STRING);
});