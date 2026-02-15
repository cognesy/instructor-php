<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Capability\Metadata\MetadataListTool;

describe('MetadataListTool schema', function () {
    it('generates valid schema for parameterless tool', function () {
        $tool = new MetadataListTool();
        $schema = $tool->toToolSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('list_metadata');
        expect($schema['function']['description'])->toBe($tool->description());
        expect($schema['function']['parameters']['type'])->toBe('object');
        expect($schema['function']['parameters']['additionalProperties'])->toBeFalse();
        // Parameterless tools must emit "properties": {} for LLM API compatibility
        expect($schema['function']['parameters'])->toHaveKey('properties');
        expect($schema['function']['parameters']['properties'])->toBeInstanceOf(\stdClass::class);
    });
});
