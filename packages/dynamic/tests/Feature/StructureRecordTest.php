<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureBuilder;

it('exposes record api schema data withData validate toArray', function () {
    $record = StructureBuilder::define('city')
        ->string('name')
        ->int('population')
        ->build(['name' => 'Wroclaw']);

    $updated = $record->withData(['name' => 'Wroclaw', 'population' => 670000]);

    expect($record->data())->toBe(['name' => 'Wroclaw'])
        ->and($updated->schema()->name())->toBe('city')
        ->and($updated->toArray())->toBe(['name' => 'Wroclaw', 'population' => 670000])
        ->and($updated->validate()->isValid())->toBeTrue();
});

it('fails validation when required field is missing', function () {
    $record = StructureBuilder::define('city')
        ->string('name')
        ->int('population')
        ->build(['name' => 'Wroclaw']);

    $validation = $record->validate();

    expect($validation->isInvalid())->toBeTrue()
        ->and($validation->getErrorMessage())->toContain('Missing required field');
});

it('supports transitional define api', function () {
    $record = Structure::define('user', [
        \Cognesy\Dynamic\Field::string('name'),
        \Cognesy\Dynamic\Field::int('age')->optional(),
    ]);

    $populated = $record->fromArray(['name' => 'Ada']);

    expect($record->toArray())->toBe([])
        ->and($populated->toArray())->toBe(['name' => 'Ada'])
        ->and($populated->schema()->name())->toBe('user');
});
