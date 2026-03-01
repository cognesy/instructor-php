<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\Type;

final class DynamicRootSchemaFixture
{
    public function __construct(
        public string $x,
    ) {}
}

it('preserves required fields for array-shape schemas when rebuilding structure', function () {
    $schema = new ArrayShapeSchema(
        type: Type::array(),
        name: 'payload',
        description: 'Array shape payload',
        properties: [
            'x' => new ScalarSchema(Type::string(), 'x'),
        ],
        required: ['x'],
    );

    $structure = Structure::fromSchema($schema);
    $validation = $structure->validate();

    expect($validation->isInvalid())->toBeTrue()
        ->and($validation->getErrorMessage())->toContain('Missing required field');
});

it('preserves root schema type metadata when rebuilding structure', function () {
    $schema = new ObjectSchema(
        type: Type::object(DynamicRootSchemaFixture::class),
        name: 'root',
        description: 'Root schema',
        properties: [
            'x' => new ScalarSchema(Type::string(), 'x'),
        ],
        required: ['x'],
    );

    $structure = Structure::fromSchema($schema);
    $rebuiltType = TypeInfo::className($structure->schema()->type());

    expect($rebuiltType)->toBe(DynamicRootSchemaFixture::class)
        ->and($structure->schema()->name())->toBe('root')
        ->and($structure->schema()->description())->toBe('Root schema');
});
