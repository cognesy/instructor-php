<?php declare(strict_types=1);

use Cognesy\HttpPool\Creation\HttpPoolRegistry;

it('memoizes the default pool registry and keeps derived registries isolated', function () {
    $default = HttpPoolRegistry::default();
    $derived = $default->withoutPool('curl');

    expect($default)->toBe(HttpPoolRegistry::default())
        ->and($default->poolNames())->toBe(['curl', 'guzzle', 'symfony'])
        ->and($derived->has('curl'))->toBeFalse()
        ->and($default->has('curl'))->toBeTrue();
});
