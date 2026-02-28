<?php declare(strict_types=1);

use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\NB\User as NBUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\Root;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;

// Guards regression from instructor-mahg (same-basename class collisions in $defs keys).
it('keeps distinct $defs and refs for classes sharing basename', function () {
    $factory = new SchemaFactory(useObjectReferences: true);
    $builder = new ToolCallBuilder($factory);

    $schema = $factory->schema(Root::class);
    $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $builder->onObjectRef(...));
    $toolCall = $builder->renderToolCall($jsonSchema, 'test_tool', 'test');
    $parameters = $toolCall[0]['function']['parameters'];
    $defs = $parameters['$defs'] ?? [];

    expect($defs)->toHaveCount(2);
    expect($parameters['properties']['naUser']['$ref'])->not->toBe($parameters['properties']['nbUser']['$ref']);

    $defsByClass = [];
    foreach ($defs as $key => $def) {
        $defsByClass[$def['x-php-class']] = $key;
    }

    expect($defsByClass)->toHaveKey(NAUser::class);
    expect($defsByClass)->toHaveKey(NBUser::class);
    expect($parameters['properties']['naUser']['$ref'])->toBe('#/$defs/' . $defsByClass[NAUser::class]);
    expect($parameters['properties']['nbUser']['$ref'])->toBe('#/$defs/' . $defsByClass[NBUser::class]);
});
