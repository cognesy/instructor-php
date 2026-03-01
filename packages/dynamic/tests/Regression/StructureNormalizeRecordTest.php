<?php declare(strict_types=1);

use Cognesy\Dynamic\StructureBuilder;

it('normalizes data to schema-defined keys only', function () {
    $record = StructureBuilder::define('args')
        ->string('name')
        ->bool('active', required: false)
        ->build();

    $normalized = $record->normalizeRecord([
        'name' => 'john',
        'active' => true,
        'ignored' => 'value',
    ]);

    expect($normalized)->toBe([
        'name' => 'john',
        'active' => true,
    ]);
});
