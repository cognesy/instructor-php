<?php declare(strict_types=1);

namespace Cognesy\Utils\Tests\Unit\JsonSchema;

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

describe('ToolSchema', function () {

    it('generates valid schema for tool with parameters', function () {
        $schema = ToolSchema::make(
            name: 'search',
            description: 'Search for items',
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('query', 'Search query'),
                ])
                ->withRequiredProperties(['query']),
        )->toArray();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('search');
        expect($schema['function']['description'])->toBe('Search for items');
        expect($schema['function']['parameters']['type'])->toBe('object');
        expect($schema['function']['parameters']['properties'])->toHaveKey('query');
        expect($schema['function']['parameters']['required'])->toBe(['query']);
    });

    it('generates valid schema for tool without parameters', function () {
        $schema = ToolSchema::make(
            name: 'ping',
            description: 'Ping the server',
            parameters: JsonSchema::object('parameters'),
        )->toArray();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('ping');
        expect($schema['function']['parameters']['type'])->toBe('object');
        expect($schema['function']['parameters'])->toHaveKey('properties');
        expect($schema['function']['parameters']['properties'])->toBeInstanceOf(\stdClass::class);

        // Must produce valid JSON with "properties": {} for LLM APIs
        $json = json_encode($schema);
        expect($json)->toContain('"properties":{}');
        expect($json)->not->toContain('"properties":[]');
    });

    it('generates valid schema for tool with optional parameters only', function () {
        $schema = ToolSchema::make(
            name: 'info',
            description: 'Get info',
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('category', 'Category to check'),
                ])
                ->withRequiredProperties([]),
        )->toArray();

        expect($schema['function']['parameters']['properties'])->toHaveKey('category');
        expect($schema['function']['parameters'])->not->toHaveKey('required');
    });

    it('round-trips through fromArray and toArray', function () {
        $original = ToolSchema::make(
            name: 'test_tool',
            description: 'A test tool',
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('input', 'The input'),
                    JsonSchema::integer('count', 'How many'),
                ])
                ->withRequiredProperties(['input']),
        );

        $array = $original->toArray();
        $restored = ToolSchema::fromArray($array['function']);

        expect($restored->name)->toBe('test_tool');
        expect($restored->description)->toBe('A test tool');

        $restoredArray = $restored->toArray();
        expect($restoredArray['function']['parameters']['properties'])->toHaveKey('input');
        expect($restoredArray['function']['parameters']['properties'])->toHaveKey('count');
    });

});
