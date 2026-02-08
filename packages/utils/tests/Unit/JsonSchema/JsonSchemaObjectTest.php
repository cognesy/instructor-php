<?php declare(strict_types=1);

namespace Cognesy\Utils\Tests\Unit\JsonSchema;

use Cognesy\Utils\JsonSchema\JsonSchema;

describe('JsonSchema::object()', function () {

    it('creates object schema', function () {
        $schema = JsonSchema::object('params');
        $array = $schema->toArray();

        expect($array['type'])->toBe('object');
        expect($array['additionalProperties'])->toBeFalse();
    });

    it('includes properties', function () {
        $schema = JsonSchema::object('user')
            ->withProperties([
                JsonSchema::string('name', 'User name'),
                JsonSchema::integer('age', 'User age'),
            ]);
        $array = $schema->toArray();

        expect($array['properties'])->toHaveKey('name');
        expect($array['properties'])->toHaveKey('age');
        expect($array['properties']['name']['type'])->toBe('string');
        expect($array['properties']['age']['type'])->toBe('integer');
    });

    it('includes required properties', function () {
        $schema = JsonSchema::object('user')
            ->withProperties([
                JsonSchema::string('name'),
                JsonSchema::string('email'),
            ])
            ->withRequiredProperties(['name', 'email']);
        $array = $schema->toArray();

        expect($array['required'])->toBe(['name', 'email']);
    });

    it('omits empty properties', function () {
        $schema = JsonSchema::object('empty');
        $array = $schema->toArray();

        expect($array)->not->toHaveKey('properties');
        expect($array)->not->toHaveKey('required');
    });

    it('supports nested objects', function () {
        $schema = JsonSchema::object('root')
            ->withProperties([
                JsonSchema::object('nested')
                    ->withProperties([
                        JsonSchema::string('value'),
                    ]),
            ]);
        $array = $schema->toArray();

        expect($array['properties']['nested']['type'])->toBe('object');
        expect($array['properties']['nested']['properties']['value']['type'])->toBe('string');
    });

    it('allows additionalProperties when specified', function () {
        $schema = JsonSchema::object('flexible', additionalProperties: true);
        $array = $schema->toArray();

        expect($array['additionalProperties'])->toBeTrue();
    });

});
