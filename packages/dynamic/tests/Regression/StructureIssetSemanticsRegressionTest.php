<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

it('reports presence for __isset based on data or non-null defaults', function () {
    $schema = SchemaBuilder::define('person')
        ->string('name')
        ->withProperty(
            'nickname',
            SchemaFactory::default()->fromType(
                type: Type::string(),
                name: 'nickname',
                hasDefaultValue: true,
                defaultValue: 'guest',
            ),
            required: false,
        )
        ->string('bio', required: false)
        ->schema();

    $empty = Structure::fromSchema($schema);
    $withNull = Structure::fromSchema($schema, ['bio' => null]);
    $withValue = Structure::fromSchema($schema, ['bio' => 'hello']);

    expect(isset($empty->name))->toBeFalse();
    expect(isset($empty->nickname))->toBeTrue();
    expect(isset($empty->bio))->toBeFalse();
    expect(isset($withNull->bio))->toBeFalse();
    expect(isset($withValue->bio))->toBeTrue();
});
