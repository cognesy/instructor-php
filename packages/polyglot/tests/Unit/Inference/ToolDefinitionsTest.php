<?php

use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;

it('constructs tool definitions from typed tool instances', function () {
    $tools = new ToolDefinitions(
        new ToolDefinition('search', 'Searches the index', ['type' => 'object']),
        new ToolDefinition('lookup', 'Looks up a record', ['type' => 'object']),
    );

    expect($tools->count())->toBe(2)
        ->and($tools->isEmpty())->toBeFalse()
        ->and($tools->all())->toHaveCount(2);
});

it('hydrates tool definitions from OpenAI tool arrays', function () {
    $tools = ToolDefinitions::fromArray([
        [
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => 'Searches the index',
                'parameters' => ['type' => 'object'],
            ],
        ],
    ]);

    expect($tools->count())->toBe(1)
        ->and($tools->all()[0]->name())->toBe('search');
});

it('round-trips tool definitions arrays', function () {
    $data = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => 'Searches the index',
                'parameters' => ['type' => 'object'],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'lookup',
                'description' => 'Looks up a record',
                'parameters' => ['type' => 'object'],
            ],
        ],
    ];

    expect(ToolDefinitions::fromArray($data)->toArray())->toBe($data);
});

it('serializes keyed tool definitions as a JSON list', function () {
    $tools = new ToolDefinitions(...[
        'search' => new ToolDefinition('search', 'Searches the index', ['type' => 'object']),
        'lookup' => new ToolDefinition('lookup', 'Looks up a record', ['type' => 'object']),
    ]);

    $data = $tools->toArray();

    expect(array_is_list($data))->toBeTrue()
        ->and($data[0]['function']['name'])->toBe('search')
        ->and($data[1]['function']['name'])->toBe('lookup');
});

it('provides a consistent empty collection', function () {
    $tools = ToolDefinitions::empty();

    expect($tools->isEmpty())->toBeTrue()
        ->and($tools->count())->toBe(0)
        ->and($tools->all())->toBe([])
        ->and($tools->toArray())->toBe([]);
});
