<?php declare(strict_types=1);

use Cognesy\Instructor\Laravel\Testing\InferenceFake;
use Cognesy\Messages\Messages;

it('normalizes array default response to JSON string', function () {
    $fake = new InferenceFake([
        'default' => ['answer' => 42, 'ok' => true],
    ]);

    $response = $fake->withMessages(Messages::fromString('anything'))->get();

    expect($response)->toBe('{"answer":42,"ok":true}')
        ->and(json_decode($response, true))->toBe([
            'answer' => 42,
            'ok' => true,
        ]);
});
