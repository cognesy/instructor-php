<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

it('does not mutate original structure when fromArray is called', function () {
    $schema = SchemaBuilder::define('user')->string('name')->schema();
    $empty = Structure::fromSchema($schema);
    $filled = $empty->fromArray(['name' => 'Ada']);

    expect($empty->toArray())->toBe([])
        ->and($filled->toArray())->toBe(['name' => 'Ada']);
});
