<?php declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;

it('does not mutate original structure when fromArray is called', function () {
    $empty = Structure::define('user', [Field::string('name')]);
    $filled = $empty->fromArray(['name' => 'Ada']);

    expect($empty->toArray())->toBe([])
        ->and($filled->toArray())->toBe(['name' => 'Ada']);
});

