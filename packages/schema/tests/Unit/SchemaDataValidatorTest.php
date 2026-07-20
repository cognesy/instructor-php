<?php declare(strict_types=1);

use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\Validation\SchemaDataValidator;
use Symfony\Component\TypeInfo\Type;

enum SchemaDataValidatorStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}

function validationTestSchema(): \Cognesy\Schema\Data\Schema
{
    return SchemaBuilder::define('issue')
        ->option('status', ['open', 'closed'])
        ->int('count')
        ->string('note', required: false)
        ->schema();
}

it('accepts data matching required scalar and option constraints', function () {
    $result = (new SchemaDataValidator(validationTestSchema()))->validate([
        'status' => 'open',
        'count' => 2,
    ]);

    expect($result->isValid())->toBeTrue();
});

it('rejects raw scalar option values outside enumValues', function () {
    $result = (new SchemaDataValidator(validationTestSchema()))->validate([
        'status' => 'pending',
        'count' => 2,
    ]);

    expect($result->isInvalid())->toBeTrue()
        ->and($result->getErrors()[0]->field)->toBe('status')
        ->and($result->getErrors()[0]->message)->toContain('enum/options');
});

it('reports missing and wrong-type fields with stable paths', function () {
    $result = (new SchemaDataValidator(validationTestSchema()))->validate([
        'status' => 'open',
        'count' => 'two',
    ]);
    $missing = (new SchemaDataValidator(validationTestSchema()))->validate([
        'count' => 2,
    ]);

    expect($result->getErrors()[0]->field)->toBe('count')
        ->and($missing->getErrors()[0]->field)->toBe('status');
});

it('distinguishes optional fields from nullable values', function () {
    $factory = SchemaFactory::default();
    $schema = SchemaBuilder::define('nullable_issue')
        ->string('optional_note', required: false)
        ->withProperty(
            'nullable_note',
            $factory->propertySchema(Type::string(), 'nullable_note', '', nullable: true),
        )
        ->string('required_note')
        ->schema();

    $valid = (new SchemaDataValidator($schema))->validate([
        'nullable_note' => null,
        'required_note' => 'present',
    ]);
    $invalid = (new SchemaDataValidator($schema))->validate([
        'nullable_note' => null,
        'required_note' => null,
    ]);

    expect($valid->isValid())->toBeTrue()
        ->and($invalid->getErrors())->toHaveCount(1)
        ->and($invalid->getErrors()[0]->field)->toBe('required_note')
        ->and($invalid->getErrors()[0]->message)->toContain('cannot be null');
});

it('validates nested object and collection values with item paths', function () {
    $item = SchemaBuilder::define('item')
        ->string('name')
        ->int('count')
        ->schema();
    $schema = SchemaBuilder::define('payload')
        ->shape('metadata', fn(SchemaBuilder $shape): SchemaBuilder => $shape
            ->string('owner')
            ->bool('active'))
        ->collection('items', $item)
        ->schema();

    $result = (new SchemaDataValidator($schema))->validate([
        'metadata' => ['owner' => 'Ada', 'active' => 'yes'],
        'items' => [
            ['name' => 'first', 'count' => 1],
            ['name' => 'second', 'count' => 'two'],
        ],
    ]);

    expect(array_map(
        static fn($error): string => $error->field,
        $result->getErrors(),
    ))->toBe(['metadata.active', 'items.1.count']);
});

it('validates scalar, array, and object wire types', function () {
    $schema = SchemaBuilder::define('wire_types')
        ->int('integer')
        ->float('number')
        ->string('text')
        ->bool('flag')
        ->array('values')
        ->object('record', stdClass::class)
        ->schema();
    $validator = new SchemaDataValidator($schema);

    expect($validator->validate([
        'integer' => 1,
        'number' => 2,
        'text' => 'ok',
        'flag' => true,
        'values' => [],
        'record' => ['key' => 'value'],
    ])->isValid())->toBeTrue();

    $invalid = $validator->validate([
        'integer' => 1.5,
        'number' => '2.0',
        'text' => 3,
        'flag' => 1,
        'values' => 'not-an-array',
        'record' => 'not-an-object',
    ]);

    expect(array_map(
        static fn($error): string => $error->field,
        $invalid->getErrors(),
    ))->toBe(['integer', 'number', 'text', 'flag', 'values', 'record']);
});

it('accepts integers as float wire values', function () {
    $schema = SchemaBuilder::define('measurement')
        ->float('value')
        ->schema();

    expect((new SchemaDataValidator($schema))->validate(['value' => 2])->isValid())->toBeTrue();
});

it('validates integer options and backed PHP enum values', function () {
    $schema = SchemaBuilder::define('typed_options')
        ->option('rank', [1, 2])
        ->enum('status', SchemaDataValidatorStatus::class)
        ->schema();
    $validator = new SchemaDataValidator($schema);

    expect($validator->validate(['rank' => 2, 'status' => 'open'])->isValid())->toBeTrue();

    $invalid = $validator->validate(['rank' => 3, 'status' => 'pending']);
    expect(array_map(
        static fn($error): string => $error->field,
        $invalid->getErrors(),
    ))->toBe(['rank', 'status']);
});

it('validates date-time values at the wire boundary', function () {
    $factory = SchemaFactory::default();
    $schema = SchemaBuilder::define('dated')
        ->withProperty('created_at', $factory->fromType(Type::object(DateTimeImmutable::class)))
        ->schema();
    $validator = new SchemaDataValidator($schema);

    expect($validator->validate(['created_at' => '2026-07-20T10:00:00+00:00'])->isValid())->toBeTrue()
        ->and($validator->validate(['created_at' => 123])->getErrors()[0]->field)->toBe('created_at');
});

it('rejects non-object and non-collection containers at their paths', function () {
    $schema = SchemaBuilder::define('containers')
        ->shape('metadata', fn(SchemaBuilder $shape): SchemaBuilder => $shape->string('owner'))
        ->collection('items', Type::string())
        ->schema();

    $result = (new SchemaDataValidator($schema))->validate([
        'metadata' => 'not-an-object',
        'items' => 'not-a-collection',
    ]);

    expect(array_map(
        static fn($error): string => $error->field,
        $result->getErrors(),
    ))->toBe(['metadata', 'items']);
});

it('does not claim to enforce unsupported JSON Schema keywords', function () {
    $schema = (new JsonSchemaParser())->fromJsonSchema([
        'type' => 'object',
        'properties' => [
            'code' => [
                'type' => 'string',
                'pattern' => '^[A-Z]+$',
            ],
        ],
        'required' => ['code'],
    ]);

    $result = (new SchemaDataValidator($schema))->validate(['code' => 'lowercase']);

    expect($result->isValid())->toBeTrue();
});
