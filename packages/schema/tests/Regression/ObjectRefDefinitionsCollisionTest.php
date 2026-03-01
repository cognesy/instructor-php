<?php declare(strict_types=1);

use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\NB\User as NBUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\Root;
use Cognesy\Schema\Tests\Support\ToolCallSchemaFixtureBuilder;

// Guards regression from instructor-mahg (same-basename class collisions in $defs keys).
it('keeps distinct $defs and refs for classes sharing basename', function () {
    $factory = new SchemaFactory(useObjectReferences: true);
    $schema = $factory->schema(Root::class);
    $toolCall = ToolCallSchemaFixtureBuilder::render($factory, $schema, 'test_tool', 'test');
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
