<?php declare(strict_types=1);

use Cognesy\Schema\Contracts\CanParseJsonSchema;
use Cognesy\Schema\Contracts\CanRenderJsonSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Symfony\Component\TypeInfo\Type;

it('uses injected renderer contract in schema factory', function () {
    $renderer = new class implements CanRenderJsonSchema {
        public function render(Schema $schema, ?callable $onObjectRef = null) : JsonSchema {
            return JsonSchema::document(['kind' => 'custom', 'name' => $schema->name()]);
        }
    };
    $factory = new SchemaFactory(schemaRenderer: $renderer);
    $schema = $factory->string('answer', 'The answer');

    expect($factory->toJsonSchema($schema))->toBe([
        'kind' => 'custom',
        'name' => 'answer',
    ]);
});

it('exposes parser and renderer contracts from schema factory', function () {
    $factory = new SchemaFactory();

    expect($factory->schemaParser())->toBeInstanceOf(CanParseJsonSchema::class);
    expect($factory->schemaRenderer())->toBeInstanceOf(CanRenderJsonSchema::class);
});

it('keeps parser compatibility via parse and fromJsonSchema methods', function () {
    $parser = new JsonSchemaParser();
    $json = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
        'required' => ['id'],
    ];

    $viaParse = SchemaFactory::withMetadata(
        $parser->parse(JsonSchema::fromArray($json)),
        name: 'Root',
        description: 'Root schema',
    );
    $viaLegacy = $parser->fromJsonSchema($json, 'Root', 'Root schema');

    expect($viaParse->toArray())->toBe($viaLegacy->toArray());
});

it('can build schema from symfony type-info type', function () {
    $schema = (new SchemaFactory())->fromType(Type::int(), 'count', 'Counter value');

    expect($schema->name())->toBe('count');
    expect($schema->description())->toBe('Counter value');
    expect((string) TypeInfo::normalize($schema->type))->toBe((string) Type::int());
});
