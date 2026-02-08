<?php declare(strict_types=1);

namespace Cognesy\Utils\Tests\Unit\JsonSchema;

use Cognesy\Utils\JsonSchema\JsonSchema;

describe('JsonSchema::string()', function () {

    it('creates string schema', function () {
        $schema = JsonSchema::string('name', 'User name');
        $array = $schema->toArray();

        expect($array['type'])->toBe('string');
        expect($array['description'])->toBe('User name');
    });

    it('omits empty description', function () {
        $schema = JsonSchema::string('name');
        $array = $schema->toArray();

        expect($array)->not->toHaveKey('description');
    });

});

describe('JsonSchema::integer()', function () {

    it('creates integer schema', function () {
        $schema = JsonSchema::integer('count', 'Item count');
        $array = $schema->toArray();

        expect($array['type'])->toBe('integer');
        expect($array['description'])->toBe('Item count');
    });

    it('includes meta constraints', function () {
        $schema = JsonSchema::integer('limit', 'Max items', meta: ['minimum' => 1, 'maximum' => 100]);
        $array = $schema->toArray();

        expect($array['x-minimum'])->toBe(1);
        expect($array['x-maximum'])->toBe(100);
    });

});

describe('JsonSchema::boolean()', function () {

    it('creates boolean schema', function () {
        $schema = JsonSchema::boolean('enabled', 'Is enabled');
        $array = $schema->toArray();

        expect($array['type'])->toBe('boolean');
        expect($array['description'])->toBe('Is enabled');
    });

});

describe('JsonSchema::number()', function () {

    it('creates number schema', function () {
        $schema = JsonSchema::number('price', 'Item price');
        $array = $schema->toArray();

        expect($array['type'])->toBe('number');
        expect($array['description'])->toBe('Item price');
    });

});

describe('JsonSchema::enum()', function () {

    it('creates enum schema with values', function () {
        $schema = JsonSchema::enum('status', ['pending', 'active', 'done'], 'Task status');
        $array = $schema->toArray();

        expect($array['type'])->toBe('string');
        expect($array['enum'])->toBe(['pending', 'active', 'done']);
        expect($array['description'])->toBe('Task status');
    });

    it('works with dynamic values', function () {
        $values = ['a', 'b', 'c'];
        $schema = JsonSchema::enum('choice', $values);
        $array = $schema->toArray();

        expect($array['enum'])->toBe(['a', 'b', 'c']);
    });

});
