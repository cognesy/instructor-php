<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchema;

// Guards 2.0 contract: optional does not imply nullable during fromArray().
it('does not infer nullable from required flags when property nullable is not explicitly provided', function () {
    $schema = JsonSchema::fromArray([
        'type' => 'object',
        'properties' => [
            'requiredField' => ['type' => 'string'],
            'optionalField' => ['type' => 'string'],
            'explicitNullableFalse' => ['type' => 'string', 'nullable' => false],
            'explicitNullableTrue' => ['type' => 'string', 'nullable' => true],
        ],
        'required' => ['requiredField'],
    ]);

    expect($schema->property('requiredField')?->isNullable())->toBeFalse()
        ->and($schema->property('optionalField')?->isNullable())->toBeFalse()
        ->and($schema->property('explicitNullableFalse')?->isNullable())->toBeFalse()
        ->and($schema->property('explicitNullableTrue')?->isNullable())->toBeTrue();
});
