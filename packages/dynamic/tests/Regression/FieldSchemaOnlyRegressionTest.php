<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\Type;

it('exposes structure as schema-first api without legacy define/builder methods', function () {
    expect(method_exists(Structure::class, 'define'))->toBeFalse()
        ->and(method_exists(Structure::class, 'builder'))->toBeFalse();
});

it('validates strictly against schema constraints', function () {
    $schema = SchemaBuilder::define('person')
        ->int('age')
        ->schema();
    $structure = Structure::fromSchema($schema);

    $invalid = $structure->withData(['age' => 'invalid'])->validate();
    $valid = $structure->withData(['age' => 30])->validate();

    expect($invalid->isInvalid())->toBeTrue()
        ->and($valid->isValid())->toBeTrue();
});

it('reads defaults from schema metadata', function () {
    $schema = SchemaBuilder::define('person')
        ->withProperty(
            'age',
            SchemaFactory::default()->fromType(
                type: Type::int(),
                name: 'age',
                hasDefaultValue: true,
                defaultValue: 21,
            ),
            required: false,
        )
        ->schema();
    $structure = Structure::fromSchema($schema);

    expect($structure->get('age'))->toBe(21)
        ->and($schema->getPropertySchema('age')->type()->isIdentifiedBy(TypeIdentifier::INT))->toBeTrue();
});
