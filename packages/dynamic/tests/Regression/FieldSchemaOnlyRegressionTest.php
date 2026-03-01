<?php declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\Data\CollectionSchema;
use Symfony\Component\TypeInfo\Type;

it('exposes field as schema-only api without per-field runtime value methods', function () {
    expect(method_exists(Field::class, 'get'))->toBeFalse()
        ->and(method_exists(Field::class, 'set'))->toBeFalse()
        ->and(method_exists(Field::class, 'withValue'))->toBeFalse();
});

it('validates field constraints against structure data', function () {
    $structure = Structure::define('person', [
        Field::int('age')->validIf(fn(mixed $value): bool => is_int($value) && $value > 0, 'Age must be positive'),
    ]);

    $invalid = $structure->withData(['age' => -1])->validate();
    $valid = $structure->withData(['age' => 30])->validate();

    expect($invalid->isInvalid())->toBeTrue()
        ->and($invalid->getErrorMessage())->toContain('Age must be positive')
        ->and($valid->isValid())->toBeTrue();
});

it('keeps field mutators immutable and side-effect free', function () {
    $required = Field::string('name');
    $optional = $required->optional();
    $withDefault = $required->withDefaultValue('anon');

    expect($required->isRequired())->toBeTrue()
        ->and($required->hasDefaultValue())->toBeFalse()
        ->and($optional->isOptional())->toBeTrue()
        ->and($withDefault->defaultValue())->toBe('anon')
        ->and($withDefault->hasDefaultValue())->toBeTrue();
});

it('treats null as explicit default value when configured', function () {
    $field = Field::string('name')->withDefaultValue(null);
    $withoutDefault = Field::string('name');

    expect($field->hasDefaultValue())->toBeTrue()
        ->and($field->defaultValue())->toBeNull()
        ->and($withoutDefault->hasDefaultValue())->toBeFalse();
});

it('preserves nested type shape when collection is defined from Type object', function () {
    $itemType = Type::list(Type::string());
    $field = Field::collection('matrix', $itemType);
    $schema = $field->schema();

    expect($schema)->toBeInstanceOf(CollectionSchema::class)
        ->and((string) $schema->nestedItemSchema->type())->toBe((string) $itemType);
});
