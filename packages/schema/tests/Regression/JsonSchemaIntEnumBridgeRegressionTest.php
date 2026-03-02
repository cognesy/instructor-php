<?php declare(strict_types=1);

use Cognesy\Schema\SchemaFactory;

enum JsonSchemaIntEnumBridgeStatus : int {
    case Draft = 1;
    case Active = 2;
    case Archived = 3;
}

it('renders int-backed enum values without string coercion', function () {
    $schema = SchemaFactory::default()->enum(JsonSchemaIntEnumBridgeStatus::class, 'status');

    $json = SchemaFactory::default()->toJsonSchema($schema);

    expect($json['type'] ?? null)->toBe('integer');
    expect($json['enum'] ?? null)->toBe([1, 2, 3]);
});
