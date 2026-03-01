<?php declare(strict_types=1);

use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

it('keeps schema nodes free of mutation methods', function () {
    expect(method_exists(Schema::class, 'withName'))->toBeFalse();
    expect(method_exists(Schema::class, 'withDescription'))->toBeFalse();
    expect(method_exists(Schema::class, 'removeProperty'))->toBeFalse();
    expect(method_exists(Schema::class, 'clone'))->toBeFalse();
    expect(method_exists(Schema::class, 'undefined'))->toBeFalse();
});

it('copies object schema metadata via factory while preserving structure', function () {
    $property = new ScalarSchema(Type::string(), 'title', 'Title');
    $original = new ObjectSchema(
        type: Type::object(\stdClass::class),
        name: 'Original',
        description: 'Original description',
        properties: ['title' => $property],
        required: ['title'],
    );

    $updated = SchemaFactory::withMetadata(
        $original,
        name: 'Renamed',
        description: 'Updated description',
    );

    expect($updated)->toBeInstanceOf(ObjectSchema::class);
    expect($updated)->not->toBe($original);
    expect($updated->name)->toBe('Renamed');
    expect($updated->description)->toBe('Updated description');
    expect($updated->properties)->toBe($original->properties);
    expect($updated->required)->toBe($original->required);
    expect($original->name)->toBe('Original');
    expect($original->description)->toBe('Original description');
});

it('copies collection schema metadata via factory while preserving nested schema', function () {
    $nested = new ScalarSchema(Type::int(), 'item', 'Item');
    $original = new CollectionSchema(
        type: Type::list(Type::int()),
        name: 'Items',
        description: 'List of items',
        nestedItemSchema: $nested,
    );

    $updated = SchemaFactory::withMetadata($original, name: 'Numbers');

    expect($updated)->toBeInstanceOf(CollectionSchema::class);
    expect($updated)->not->toBe($original);
    expect($updated->name)->toBe('Numbers');
    expect($updated->description)->toBe('List of items');
    expect($updated->nestedItemSchema)->toBe($nested);
});

