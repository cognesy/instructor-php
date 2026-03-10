<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;

class TransitiveDefsLeaf
{
    public string $value;
}

class TransitiveDefsMiddle
{
    public TransitiveDefsLeaf $leaf;
}

class TransitiveDefsRoot
{
    public TransitiveDefsMiddle $middle;
}

it('includes transitive object refs in tool-call $defs when object references are enabled', function () {
    $renderer = new StructuredOutputSchemaRenderer(
        new StructuredOutputConfig(useObjectReferences: true),
    );

    $schema = $renderer->schemaFactory()->schema(TransitiveDefsRoot::class);
    $rendering = $renderer->renderFromSchema($schema);
    $parameters = $rendering->toolDefinitions()->all()[0]->parameters();
    $defs = $parameters['$defs'] ?? [];

    expect($parameters['properties']['middle']['$ref'] ?? null)->toBe('#/$defs/TransitiveDefsMiddle');
    expect($defs)->toHaveKey('TransitiveDefsMiddle');
    expect($defs['TransitiveDefsMiddle']['properties']['leaf']['$ref'] ?? null)->toBe('#/$defs/TransitiveDefsLeaf');
    expect($defs)->toHaveKey('TransitiveDefsLeaf');
});

