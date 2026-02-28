<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Instructor\Tests\Examples\RefsCollision\NB\User as NBUser;
use Cognesy\Instructor\Tests\Examples\RefsCollision\Root as CollisionRoot;

class StructuredOutputSelfReferencingNode
{
    public string $name;
    public ?StructuredOutputSelfReferencingNode $parent = null;
}

it('renders tool-call schema with distinct refs for same-basename classes when object references are enabled', function () {
    $renderer = new StructuredOutputSchemaRenderer(
        new StructuredOutputConfig(useObjectReferences: true),
    );

    $schema = $renderer->schemaFactory()->schema(CollisionRoot::class);
    $rendering = $renderer->renderFromSchema($schema);
    $parameters = $rendering->toolCallSchema()[0]['function']['parameters'];
    $defs = $parameters['$defs'] ?? [];

    $defsByClass = [];
    foreach ($defs as $defKey => $def) {
        $class = $def['x-php-class'] ?? '';
        if (!is_string($class) || $class === '') {
            continue;
        }
        $defsByClass[$class] = $defKey;
    }

    expect($defsByClass)->toHaveKey(NAUser::class);
    expect($defsByClass)->toHaveKey(NBUser::class);
    expect($parameters['properties']['naUser']['$ref'] ?? null)->toBe('#/$defs/' . $defsByClass[NAUser::class]);
    expect($parameters['properties']['nbUser']['$ref'] ?? null)->toBe('#/$defs/' . $defsByClass[NBUser::class]);
    expect($parameters['properties']['naUser']['$ref'] ?? null)->not->toBe($parameters['properties']['nbUser']['$ref'] ?? null);
});

it('terminates self-referential schema rendering in default mode', function () {
    $renderer = new StructuredOutputSchemaRenderer(new StructuredOutputConfig());
    $schema = $renderer->schemaFactory()->schema(StructuredOutputSelfReferencingNode::class);
    $rendering = $renderer->renderFromSchema($schema);

    $json = $rendering->jsonSchema();
    $parent = $json['properties']['parent'] ?? [];
    $cycleCut = $parent['properties']['parent'] ?? [];

    expect($json['type'] ?? null)->toBe('object');
    expect($json['properties'] ?? [])->toHaveKey('parent');
    expect($parent['type'] ?? null)->toBe('object');
    expect($parent['properties'] ?? [])->toHaveKey('name');
    expect($parent['properties'] ?? [])->toHaveKey('parent');
    expect($cycleCut['type'] ?? null)->toBe('object');
    expect($cycleCut['properties'] ?? [])->toBe([]);
    expect($rendering->toolCallSchema())->toHaveCount(1);
});

