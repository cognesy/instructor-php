<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
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
    public array $arrayProperty;
    /** @var string[] */
    public array $collectionProperty;
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
        Field::array('arrayProperty', 'Array property'),
        Field::collection('collectionProperty', TypeDetails::PHP_STRING, 'Array property'),
        Field::collection('collectionObjectProperty', TestNestedObject::class, 'Array object property'),
        Field::collection('collectionEnumProperty', TestEnum::class, 'Array enum property'),
    ]);
    expect($structure->fields())->toHaveCount(11);
    expect($structure->field('stringProperty')->name())->toBe('stringProperty');
    expect($structure->field('integerProperty')->name())->toBe('integerProperty');
    expect($structure->field('boolProperty')->name())->toBe('boolProperty');
    expect($structure->field('floatProperty')->name())->toBe('floatProperty');
    expect($structure->field('datetimeProperty')->name())->toBe('datetimeProperty');
    expect($structure->field('enumProperty')->name())->toBe('enumProperty');
    expect($structure->field('objectProperty')->name())->toBe('objectProperty');
    expect($structure->field('arrayProperty')->name())->toBe('arrayProperty');
    expect($structure->field('collectionProperty')->name())->toBe('collectionProperty');
    expect($structure->field('collectionObjectProperty')->name())->toBe('collectionObjectProperty');
    expect($structure->field('collectionEnumProperty')->name())->toBe('collectionEnumProperty');
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
        Field::array('arrayProperty', 'Array property'),
        Field::collection('collectionProperty', TypeDetails::PHP_STRING, 'Collection property'),
        Field::collection('collectionObjectProperty', TestNestedObject::class, 'Collection object property'),
        Field::collection('collectionEnumProperty', TestEnum::class, 'Collection enum property'),
    ]);

    $structure->stringProperty = 'string';
    $structure->integerProperty = 1;
    $structure->boolProperty = true;
    $structure->floatProperty = 1.1;
    $structure->datetimeProperty = new DateTime('2020-10-01');
    $structure->enumProperty = TestEnum::A;
    $structure->objectProperty = new TestNestedObject();
    $structure->arrayProperty = ['a', 'b', 1];
    $structure->collectionProperty = ['a', 'b', 'c'];
    $structure->collectionObjectProperty = [new TestNestedObject(), new TestNestedObject()];
    $structure->collectionEnumProperty = [TestEnum::A, TestEnum::B];

    $data = $structure->toArray();
    expect($data)->toHaveCount(11);
    expect($data['stringProperty'])->toBe('string');
    expect($data['integerProperty'])->toBe(1);
    expect($data['boolProperty'])->toBe(true);
    expect($data['floatProperty'])->toBe(1.1);
    expect($data['datetimeProperty'])->toBe('2020-10-01 00:00:00');
    expect($data['enumProperty'])->toBe(TestEnum::A->value);
    expect($data['objectProperty'])->toBe([]);
    expect($data['arrayProperty'])->toBe(['a', 'b', 1]);
    expect($data['collectionProperty'])->toBe(['a', 'b', 'c']);
    expect($data['collectionObjectProperty'])->toBeArray();
    expect($data['collectionObjectProperty'])->toHaveCount(2);
    expect($data['collectionEnumProperty'])->toBe(['A', 'B']);
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
        ], 'Structure property'),
        Field::object('objectProperty', TestNestedObject::class, 'Object property')->optional(),
        Field::array('arrayProperty', 'Array property'),
        Field::collection('collectionProperty', TypeDetails::PHP_STRING, 'Collection property'),
        Field::collection('collectionDateProperty', DateTime::class, 'Collection object property')->optional(),
        Field::collection('collectionEnumProperty', TestEnum::class, 'Collection enum property'),
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
            'arrayProperty' => ['a', 'b', 2],
        ],
        'arrayProperty' => ['a', 'b', 1],
        'collectionProperty' => ['a', 'b', 'c'],
        'collectionDateProperty' => ['2020-10-01', '2021-01-12'],
        'collectionEnumProperty' => ['A', 'B'],
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
    expect($structure->objectProperty->arrayProperty)->toBe(['a', 'b', 2]);
    expect($structure->arrayProperty)->toBe(['a', 'b', 1]);
    expect($structure->collectionProperty)->toBe(['a', 'b', 'c']);
    expect($structure->collectionDateProperty)->toBeArray();
    expect($structure->collectionDateProperty)->toHaveCount(2);
    expect($structure->collectionDateProperty[0])->toBeInstanceOf(DateTime::class);
    expect($structure->collectionDateProperty[1])->toBeInstanceOf(DateTime::class);
    expect($structure->collectionEnumProperty)->toBeArray();
    expect($structure->collectionEnumProperty)->toHaveCount(2);
    expect($structure->collectionEnumProperty[0])->toBe(TestEnum::A);
    expect($structure->collectionEnumProperty[1])->toBe(TestEnum::B);
});

it('creates structure from class', function() {
    $structure = StructureFactory::fromClass(TestNestedObject::class);

    expect($structure->fields())->toHaveCount(8);
    expect($structure->field('stringProperty')->name())->toBe('stringProperty');
    expect($structure->field('integerProperty')->name())->toBe('integerProperty');
    expect($structure->field('boolProperty')->name())->toBe('boolProperty');
    expect($structure->field('floatProperty')->name())->toBe('floatProperty');
    expect($structure->field('datetimeProperty')->name())->toBe('datetimeProperty');
    expect($structure->field('enumProperty')->name())->toBe('enumProperty');
    expect($structure->field('arrayProperty')->name())->toBe('arrayProperty');
    expect($structure->field('collectionProperty')->name())->toBe('collectionProperty');
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

    $structure = StructureFactory::fromJsonSchema($jsonSchema);

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
    $structure = StructureFactory::fromArrayKeyValues('TestStructure', [
        'stringProperty' => 'string',
        'integerProperty' => 1,
        'boolProperty' => true,
        'floatProperty' => 1.1,
        'enumProperty' => TestEnum::A->value,
        'arrayProperty' => ['a', 'b', 1],
        'collectionProperty' => ['a', 'b', 1],
    ]);

    expect($structure->fields())->toHaveCount(7);
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
    expect($structure->field('arrayProperty')->name())->toBe('arrayProperty');
    expect($structure->field('arrayProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_ARRAY);
    expect($structure->field('collectionProperty')->name())->toBe('collectionProperty');
    expect($structure->field('collectionProperty')->typeDetails()->type)->toBe(TypeDetails::PHP_ARRAY);
});
