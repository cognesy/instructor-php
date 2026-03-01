<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchemaType;

// Guards regression: untyped anyOf branches were mapped to '' and triggered invalid type exceptions.
it('ignores untyped anyOf branches when extracting json schema type', function () {
    $type = JsonSchemaType::fromJsonData([
        'anyOf' => [
            ['type' => 'string'],
            ['description' => 'branch without explicit type'],
        ],
    ]);

    expect($type->isString())->toBeTrue();
});

it('treats fully untyped anyOf as any type', function () {
    $type = JsonSchemaType::fromJsonData([
        'anyOf' => [
            ['description' => 'first untyped branch'],
            ['const' => 'fixed'],
        ],
    ]);

    expect($type->isAny())->toBeTrue();
});
