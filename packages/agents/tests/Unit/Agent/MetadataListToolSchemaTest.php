<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Capability\Metadata\MetadataListTool;

describe('MetadataListTool schema', function () {
    it('generates valid schema for parameterless tool', function () {
        $tool = new MetadataListTool();
        $schema = $tool->toToolSchema();

        expect($schema->name())->toBe('list_metadata');
        expect($schema->description())->toBe($tool->description());
        expect($schema->parameters()['type'])->toBe('object');
        expect($schema->parameters()['additionalProperties'])->toBeFalse();
        // Parameterless tools must emit "properties": {} for LLM API compatibility
        expect($schema->parameters())->toHaveKey('properties');
        expect($schema->parameters()['properties'])->toBeInstanceOf(\stdClass::class);
    });
});
