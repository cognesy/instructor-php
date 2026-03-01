<?php declare(strict_types=1);

use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\TransitiveRefs\Leaf;
use Cognesy\Schema\Tests\Examples\TransitiveRefs\Middle;
use Cognesy\Schema\Tests\Examples\TransitiveRefs\Root;
use Cognesy\Schema\Tests\Support\ToolCallSchemaFixtureBuilder;

// Guards regression from instructor-pf02 (nested refs completeness in tool-call schemas).
it('generates transitive $defs and resolves all internal refs for nested object references', function () {
    $factory = new SchemaFactory(useObjectReferences: true);
    $schema = $factory->schema(Root::class);
    $toolCall = ToolCallSchemaFixtureBuilder::render($factory, $schema, 'test_tool', 'test');
    $parameters = $toolCall[0]['function']['parameters'];
    $defs = $parameters['$defs'] ?? [];

    $defsByClass = [];
    foreach ($defs as $key => $def) {
        $defsByClass[$def['x-php-class'] ?? ''] = $key;
    }

    expect($defsByClass)->toHaveKey(Middle::class);
    expect($defsByClass)->toHaveKey(Leaf::class);
    expect($parameters['properties']['middle']['$ref'] ?? null)->toBe('#/$defs/' . $defsByClass[Middle::class]);
    expect($defs[$defsByClass[Middle::class]]['properties']['leaf']['$ref'] ?? null)->toBe('#/$defs/' . $defsByClass[Leaf::class]);

    $refTargets = [];
    $collectRefs = function (array $node) use (&$collectRefs, &$refTargets): void {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $collectRefs($value);
            }
            if ($key !== '$ref' || !is_string($value)) {
                continue;
            }
            if (!str_starts_with($value, '#/$defs/')) {
                continue;
            }
            $refTargets[] = substr($value, strlen('#/$defs/'));
        }
    };
    $collectRefs($parameters);

    $defKeys = array_keys($defs);
    foreach ($refTargets as $refTarget) {
        expect(in_array($refTarget, $defKeys, true))->toBeTrue();
    }
});
