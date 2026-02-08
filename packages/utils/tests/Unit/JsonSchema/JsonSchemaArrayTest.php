<?php declare(strict_types=1);

namespace Cognesy\Utils\Tests\Unit\JsonSchema;

use Cognesy\Utils\JsonSchema\JsonSchema;

describe('JsonSchema::array()', function () {

    it('creates array schema with string items', function () {
        $schema = JsonSchema::array('tags', JsonSchema::string(), 'List of tags');
        $array = $schema->toArray();

        expect($array['type'])->toBe('array');
        expect($array['items']['type'])->toBe('string');
        expect($array['description'])->toBe('List of tags');
    });

    it('creates array schema with object items', function () {
        $schema = JsonSchema::array(
            'users',
            JsonSchema::object()
                ->withProperties([
                    JsonSchema::string('name'),
                    JsonSchema::string('email'),
                ])
                ->withRequiredProperties(['name', 'email'])
        );
        $array = $schema->toArray();

        expect($array['type'])->toBe('array');
        expect($array['items']['type'])->toBe('object');
        expect($array['items']['properties'])->toHaveKey('name');
        expect($array['items']['required'])->toBe(['name', 'email']);
    });

    it('omits items when not specified', function () {
        $schema = JsonSchema::array('list');
        $array = $schema->toArray();

        expect($array)->not->toHaveKey('items');
    });

});

describe('JsonSchema::collection()', function () {

    it('is alias for array', function () {
        $schema = JsonSchema::collection('items', JsonSchema::integer());
        $array = $schema->toArray();

        expect($array['type'])->toBe('array');
        expect($array['items']['type'])->toBe('integer');
    });

});
