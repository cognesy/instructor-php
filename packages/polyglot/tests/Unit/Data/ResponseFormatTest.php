<?php

use Cognesy\Polyglot\Inference\Data\ResponseFormat;

it('provides explicit constructors for text, json object, and json schema', function () {
    $text = ResponseFormat::text();
    $jsonObject = ResponseFormat::jsonObject();
    $jsonSchema = ResponseFormat::jsonSchema(
        schema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        name: 'user',
        strict: false,
    );

    expect($text->toArray())->toBe(['type' => 'text']);
    expect($jsonObject->toArray())->toBe(['type' => 'json_object']);
    expect($jsonSchema->toArray())->toBe([
        'type' => 'json_schema',
        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        'name' => 'user',
        'strict' => false,
    ]);
});

it('answers with defaults for the fields a caller left unset', function () {
    $empty = ResponseFormat::empty();

    expect($empty->isEmpty())->toBeTrue()
        ->and($empty->type())->toBe('text')
        ->and($empty->schemaName())->toBe('schema')
        ->and($empty->strict())->toBeTrue()
        ->and($empty->schema())->toBe([])
        ->and($empty->toArray())->toBe([]);
});

it('carries no rendering behaviour', function () {
    // The point of instructor-eexl.8. This used to take three injectable Closure handlers so a
    // provider could hand it its own serialisation; every request paid two closures and two
    // copies of this object for the privilege. Provider variation now lives on the body
    // formats -- see OpenAIBodyFormat::renderResponseFormatForType() and the golden fixture in
    // tests/Unit/Drivers/ResponseFormatFragmentGoldenTest.php.
    //
    // Asserted structurally because the alternative -- asserting the absence of methods one by
    // one -- cannot catch a FOURTH handler being added later.
    $constructor = (new ReflectionClass(ResponseFormat::class))->getConstructor();

    expect($constructor?->getNumberOfParameters())->toBe(4)
        ->and(array_map(fn($p) => $p->getName(), $constructor?->getParameters() ?? []))
        ->toBe(['type', 'schema', 'name', 'strict']);

    foreach ((new ReflectionClass(ResponseFormat::class))->getProperties() as $property) {
        expect((string) $property->getType())->not->toContain('Closure');
    }

    $methods = array_map(
        fn($m) => $m->getName(),
        (new ReflectionClass(ResponseFormat::class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );
    expect(array_filter($methods, fn($n) => str_starts_with($n, 'withTo')))->toBe([]);
    expect(array_filter($methods, fn($n) => str_starts_with($n, 'as')))->toBe([]);
});

it('filters the schema through a caller-supplied callback', function () {
    // Live consumer: GeminiBodyFormat, which passes its removeDisallowedEntries().
    $responseFormat = ResponseFormat::jsonSchema(
        schema: ['type' => 'object', 'x-title' => 'User'],
    );

    $filtered = $responseFormat->schemaFilteredWith(
        fn(array $schema) => array_diff_key($schema, ['x-title' => null]),
    );

    expect($filtered)->toBe(['type' => 'object'])
        // The value object is untouched -- the filter renders, it does not mutate.
        ->and($responseFormat->schema())->toBe(['type' => 'object', 'x-title' => 'User']);
});

it('round-trips from plain and nested arrays', function () {
    $plain = ResponseFormat::fromArray([
        'type' => 'json_schema',
        'schema' => ['type' => 'object'],
        'name' => 'plain',
        'strict' => false,
    ]);

    $nested = ResponseFormat::fromArray([
        'type' => 'json_schema',
        'json_schema' => [
            'schema' => ['type' => 'object'],
            'name' => 'nested',
            'strict' => true,
        ],
    ]);

    expect($plain->toArray())->toBe([
        'type' => 'json_schema',
        'schema' => ['type' => 'object'],
        'name' => 'plain',
        'strict' => false,
    ]);
    expect($nested->toArray())->toBe([
        'type' => 'json_schema',
        'schema' => ['type' => 'object'],
        'name' => 'nested',
        'strict' => true,
    ]);
});
