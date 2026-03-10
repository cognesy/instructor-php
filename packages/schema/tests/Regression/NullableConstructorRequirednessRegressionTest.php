<?php declare(strict_types=1);

use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\SchemaFactory;

final readonly class NullableConstructorRequiredFixture
{
    public function __construct(
        public ?string $requiredNullable,
        public ?string $optionalNullable = null,
    ) {}
}

it('treats nullable constructor parameters without defaults as required', function () {
    $required = PropertyInfo::fromName(NullableConstructorRequiredFixture::class, 'requiredNullable');
    $optional = PropertyInfo::fromName(NullableConstructorRequiredFixture::class, 'optionalNullable');

    expect($required->isNullable())->toBeTrue()
        ->and($required->isRequired())->toBeTrue()
        ->and($optional->isNullable())->toBeTrue()
        ->and($optional->isRequired())->toBeFalse();
});

it('includes nullable constructor parameters without defaults in generated schema required fields', function () {
    $schema = SchemaFactory::default()->schema(NullableConstructorRequiredFixture::class);
    $json = SchemaFactory::default()->toJsonSchema($schema);

    expect($json['required'] ?? [])->toBe(['requiredNullable'])
        ->and($json['properties']['requiredNullable']['nullable'] ?? null)->toBeTrue()
        ->and($json['properties']['optionalNullable']['nullable'] ?? null)->toBeTrue();
});
