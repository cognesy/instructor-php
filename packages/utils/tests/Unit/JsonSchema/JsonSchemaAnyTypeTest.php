<?php declare(strict_types=1);

namespace Cognesy\Utils\Tests\Unit\JsonSchema;

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

describe('JsonSchema::any()', function () {

    it('creates schema without type field', function () {
        $schema = JsonSchema::any('value', 'Any JSON value');
        $array = $schema->toArray();

        expect($array)->not->toHaveKey('type');
        expect($array)->toHaveKey('description');
        expect($array['description'])->toBe('Any JSON value');
    });

    it('works with empty description', function () {
        $schema = JsonSchema::any('value');
        $array = $schema->toArray();

        expect($array)->not->toHaveKey('type');
        expect($array)->not->toHaveKey('description');
    });

    it('includes meta fields', function () {
        $schema = JsonSchema::any('value', 'desc', meta: ['example' => 'test']);
        $array = $schema->toArray();

        expect($array)->toHaveKey('x-example');
        expect($array['x-example'])->toBe('test');
    });

});

describe('JsonSchemaType::any()', function () {

    it('creates type with empty types array', function () {
        $type = JsonSchemaType::any();

        expect($type->isAny())->toBeTrue();
        expect($type->isString())->toBeFalse();
        expect($type->isObject())->toBeFalse();
    });

    it('serializes to empty when converting to string', function () {
        $type = JsonSchemaType::any();

        expect($type->toString())->toBe('');
    });

});

describe('JsonSchemaType::fromJsonData() with missing type', function () {

    it('handles schema with only description', function () {
        $type = JsonSchemaType::fromJsonData(['description' => 'Any value']);

        expect($type->isAny())->toBeTrue();
    });

    it('handles empty schema', function () {
        $type = JsonSchemaType::fromJsonData([]);

        expect($type->isAny())->toBeTrue();
    });

    it('handles schema with only meta fields', function () {
        $type = JsonSchemaType::fromJsonData(['x-example' => 'test']);

        expect($type->isAny())->toBeTrue();
    });

});

describe('JsonSchema::fromArray() with typeless data', function () {

    it('parses schema without type field', function () {
        $schema = JsonSchema::fromArray([
            'description' => 'Accepts any value',
        ], 'value');

        expect($schema->name())->toBe('value');
        expect($schema->description())->toBe('Accepts any value');
        expect($schema->toArray())->not->toHaveKey('type');
    });

    it('parses empty schema', function () {
        $schema = JsonSchema::fromArray([], 'empty');

        expect($schema->name())->toBe('empty');
        expect($schema->toArray())->not->toHaveKey('type');
    });

});
