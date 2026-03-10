<?php

use Cognesy\Polyglot\Inference\Data\ToolDefinition;

it('constructs tool definition from typed fields', function () {
    $tool = new ToolDefinition(
        name: 'search',
        description: 'Searches the index',
        parameters: ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
    );

    expect($tool->name())->toBe('search')
        ->and($tool->description())->toBe('Searches the index')
        ->and($tool->parameters())->toBe(['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]);
});

it('hydrates tool definition from OpenAI tool format', function () {
    $tool = ToolDefinition::fromArray([
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'description' => 'Searches the index',
            'parameters' => ['type' => 'object'],
        ],
    ]);

    expect($tool->name())->toBe('search')
        ->and($tool->description())->toBe('Searches the index')
        ->and($tool->parameters())->toBe(['type' => 'object']);
});

it('round-trips tool definition arrays', function () {
    $data = [
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'description' => 'Searches the index',
            'parameters' => [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string']],
                'required' => ['query'],
            ],
        ],
    ];

    expect(ToolDefinition::fromArray($data)->toArray())->toBe($data);
});
