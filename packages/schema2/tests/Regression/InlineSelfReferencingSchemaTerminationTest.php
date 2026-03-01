<?php declare(strict_types=1);

use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Tests\Examples\Schema\SelfReferencingClass;

// Guards regression from instructor-hos5 (self-referential inline schema recursion blowup).
it('terminates for self-referencing classes when object references are disabled', function () {
    $factory = new SchemaFactory(useObjectReferences: false);

    $schema = $factory->schema(SelfReferencingClass::class);
    $json = $schema->toJsonSchema();
    $parent = $json['properties']['parent'] ?? [];
    $cycleCut = $parent['properties']['parent'] ?? [];

    expect($json['type'] ?? null)->toBe('object');
    expect($json['properties'] ?? [])->toHaveKey('parent');
    expect($parent['type'] ?? null)->toBe('object');
    expect($parent['properties'] ?? [])->toHaveKey('name');
    expect($parent['properties'] ?? [])->toHaveKey('parent');
    expect($cycleCut['type'] ?? null)->toBe('object');
    // Cycle handling: inline expansion is truncated on repeated class.
    expect($cycleCut['properties'] ?? [])->toBe([]);
});
