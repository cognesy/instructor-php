<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Drivers\Support\StrictJsonSchema;

it('requires every property and makes nullable fields explicit, including nested array items', function () {
    $schema = StrictJsonSchema::normalize([
        'type' => 'object',
        'properties' => [
            'list' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'school' => ['type' => 'string'],
                        'year' => ['type' => 'integer', 'nullable' => true, 'default' => null],
                        'degree' => ['type' => 'string', 'enum' => ['BSc', 'PhD'], 'nullable' => true],
                    ],
                    'required' => ['school'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['list'],
    ]);

    $item = $schema['properties']['list']['items'];

    expect($item['required'])->toBe(['school', 'year', 'degree'])
        ->and($item['properties']['year'])->toBe(['type' => ['integer', 'null'], 'default' => null])
        ->and($item['properties']['degree'])->toBe(['type' => ['string', 'null'], 'enum' => ['BSc', 'PhD', null]])
        ->and($schema['required'])->toBe(['list']);
});

it('wraps nullable schemas without a type in anyOf and leaves non-nullable schemas alone', function () {
    expect(StrictJsonSchema::normalize(['$ref' => '#/$defs/Item', 'nullable' => true]))
        ->toBe(['anyOf' => [['$ref' => '#/$defs/Item'], ['type' => 'null']]])
        ->and(StrictJsonSchema::normalize(['type' => 'string', 'nullable' => false]))
        ->toBe(['type' => 'string', 'nullable' => false]);
});
