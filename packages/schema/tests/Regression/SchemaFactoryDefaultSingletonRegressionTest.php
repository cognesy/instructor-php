<?php declare(strict_types=1);

use Cognesy\Schema\Data\ObjectRefSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;

beforeEach(fn() => SchemaFactory::flushCache());
afterEach(fn() => SchemaFactory::flushCache());

it('returns a fresh instance from default() to avoid cross-call cache sharing', function () {
    $first = SchemaFactory::default();
    $second = SchemaFactory::default();

    expect($first)->not->toBe($second);
});

// The reflection cache is process-wide so a per-request factory does not re-reflect the
// same class, but Schema is a readonly class with no mutators, so a shared entry cannot
// carry state between callers. What must not be shared is a schema built under different
// configuration -- these cover the collisions that would cause.

it('does not leak cached schemas between factories with different useObjectReferences', function () {
    $inlined = (new SchemaFactory(useObjectReferences: false))->schema(SimpleClass::class);
    $referenced = (new SchemaFactory(useObjectReferences: true))->schema(SimpleClass::class);

    expect($inlined)->toBeInstanceOf(ObjectSchema::class);
    expect($referenced)->toBeInstanceOf(ObjectSchema::class);

    // The root is always inlined; useObjectReferences only changes nested properties.
    expect($inlined->properties['nestedClassVar'])->toBeInstanceOf(ObjectSchema::class);
    expect($referenced->properties['nestedClassVar'])->toBeInstanceOf(ObjectRefSchema::class);
});

it('reuses cached schemas between identically configured factories', function () {
    $first = (new SchemaFactory())->schema(SimpleClass::class);
    $second = (new SchemaFactory())->schema(SimpleClass::class);

    expect($first)->toBe($second);
});

it('drops shared entries on flushCache', function () {
    $first = (new SchemaFactory())->schema(SimpleClass::class);
    SchemaFactory::flushCache();
    $second = (new SchemaFactory())->schema(SimpleClass::class);

    expect($first)->not->toBe($second);
    expect($first->name)->toBe($second->name);
});
