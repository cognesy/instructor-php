<?php declare(strict_types=1);

use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Tests\Examples\Schema\StaticPropertiesClass;

it('excludes static properties from generated schema properties and required list', function () {
    $factory = new SchemaFactory();
    $json = $factory->schema(StaticPropertiesClass::class)->toJsonSchema();

    $properties = $json['properties'] ?? [];
    $required = $json['required'] ?? [];

    expect($properties)->toHaveKey('name');
    expect($properties)->toHaveKey('age');
    expect($properties)->not->toHaveKey('globalName');
    expect($properties)->not->toHaveKey('globalCount');
    expect($required)->toContain('name');
    expect($required)->not->toContain('globalName');
    expect($required)->not->toContain('globalCount');
});

