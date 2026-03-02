<?php declare(strict_types=1);

use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\SchemaFactory;

class SchemaDefaultsFixture
{
    public int $fromPropertyDefault = 7;
    public ?string $nullableWithNullDefault = null;
    public readonly string $fromConstructorDefault;
    public int $requiredNoDefault;
    private string $fromSetterDefault;

    public function __construct(string $fromConstructorDefault = 'ctor-default') {
        $this->fromConstructorDefault = $fromConstructorDefault;
    }

    public function setFromSetterDefault(string $fromSetterDefault = 'setter-default') : void {
        $this->fromSetterDefault = $fromSetterDefault;
    }
}

it('extracts default metadata precedence from reflection property info', function () {
    $propertyDefault = PropertyInfo::fromName(SchemaDefaultsFixture::class, 'fromPropertyDefault');
    $nullableDefault = PropertyInfo::fromName(SchemaDefaultsFixture::class, 'nullableWithNullDefault');
    $constructorDefault = PropertyInfo::fromName(SchemaDefaultsFixture::class, 'fromConstructorDefault');
    $setterDefault = PropertyInfo::fromName(SchemaDefaultsFixture::class, 'fromSetterDefault');
    $required = PropertyInfo::fromName(SchemaDefaultsFixture::class, 'requiredNoDefault');

    expect($propertyDefault->hasDefaultValue())->toBeTrue();
    expect($propertyDefault->defaultValue())->toBe(7);
    expect($propertyDefault->isRequired())->toBeFalse();

    expect($nullableDefault->isNullable())->toBeTrue();
    expect($nullableDefault->hasDefaultValue())->toBeTrue();
    expect($nullableDefault->defaultValue())->toBeNull();
    expect($nullableDefault->isRequired())->toBeFalse();

    expect($constructorDefault->hasDefaultValue())->toBeTrue();
    expect($constructorDefault->defaultValue())->toBe('ctor-default');
    expect($constructorDefault->isRequired())->toBeFalse();

    expect($setterDefault->hasDefaultValue())->toBeTrue();
    expect($setterDefault->defaultValue())->toBe('setter-default');
    expect($setterDefault->isRequired())->toBeFalse();

    expect($required->hasDefaultValue())->toBeFalse();
    expect($required->defaultValue())->toBeNull();
    expect($required->isRequired())->toBeTrue();
});

it('propagates nullable and default metadata into property schemas', function () {
    $schema = (new SchemaFactory())->schema(SchemaDefaultsFixture::class);

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->required)->toBe(['requiredNoDefault']);
    expect($schema->properties)->toHaveKeys([
        'fromPropertyDefault',
        'nullableWithNullDefault',
        'fromConstructorDefault',
        'requiredNoDefault',
        'fromSetterDefault',
    ]);

    $propertyDefault = $schema->properties['fromPropertyDefault'];
    $nullableDefault = $schema->properties['nullableWithNullDefault'];
    $constructorDefault = $schema->properties['fromConstructorDefault'];
    $setterDefault = $schema->properties['fromSetterDefault'];
    $required = $schema->properties['requiredNoDefault'];

    expect($propertyDefault->hasDefaultValue())->toBeTrue();
    expect($propertyDefault->defaultValue())->toBe(7);
    expect($propertyDefault->isNullable())->toBeFalse();

    expect($nullableDefault->hasDefaultValue())->toBeTrue();
    expect($nullableDefault->defaultValue())->toBeNull();
    expect($nullableDefault->isNullable())->toBeTrue();

    expect($constructorDefault->hasDefaultValue())->toBeTrue();
    expect($constructorDefault->defaultValue())->toBe('ctor-default');

    expect($setterDefault->hasDefaultValue())->toBeTrue();
    expect($setterDefault->defaultValue())->toBe('setter-default');

    expect($required->hasDefaultValue())->toBeFalse();
    expect($required->isNullable())->toBeFalse();
});

it('keeps nullable flag in root schema for nullable scalar type names', function () {
    $factory = new SchemaFactory();

    $nullable = $factory->schema('?int');
    $nonNullable = $factory->schema('int');

    expect((string) $nullable->type)->toBe('int');
    expect($nullable->isNullable())->toBeTrue();
    expect($nonNullable->isNullable())->toBeFalse();
    expect($nullable)->not->toBe($nonNullable);
});

it('serializes default value key only when schema has default metadata', function () {
    $schema = (new SchemaFactory())->schema(SchemaDefaultsFixture::class);

    $nullableDefault = $schema->properties['nullableWithNullDefault']->toArray();
    $required = $schema->properties['requiredNoDefault']->toArray();

    expect($nullableDefault['hasDefaultValue'])->toBeTrue();
    expect(array_key_exists('defaultValue', $nullableDefault))->toBeTrue();
    expect($nullableDefault['defaultValue'])->toBeNull();

    expect($required['hasDefaultValue'])->toBeFalse();
    expect(array_key_exists('defaultValue', $required))->toBeFalse();
});
