<?php declare(strict_types=1);

use Cognesy\Schema\Reflection\PropertyInfo;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Schema2InputField
{
    public function __construct(
        public string $description = '',
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Schema2OutputField
{
    public function __construct(
        public string $description = '',
    ) {}
}

class SchemaDescriptionsSignatureFieldsFixture
{
    #[Schema2InputField('Input-only description')]
    public string $inputOnly;

    #[Schema2OutputField('Output-only description')]
    public string $outputOnly;
}

it('does not treat unrelated attribute text as schema property description', function () {
    $inputDescription = PropertyInfo::fromName(
        SchemaDescriptionsSignatureFieldsFixture::class,
        'inputOnly',
    )->getDescription();
    $outputDescription = PropertyInfo::fromName(
        SchemaDescriptionsSignatureFieldsFixture::class,
        'outputOnly',
    )->getDescription();

    expect($inputDescription)->toBe('');
    expect($outputDescription)->toBe('');
});
