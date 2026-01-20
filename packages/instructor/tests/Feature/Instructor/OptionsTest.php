<?php

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Instructor\Person;

it('stores non-boolean options on the request', function () {
    $pending = (new StructuredOutput())
        ->withResponseModel(Person::class)
        ->withOption('temperature', 0.2)
        ->withOption('tag', 'demo')
        ->create();

    expect($pending->execution()->request()->options())->toMatchArray([
        'temperature' => 0.2,
        'tag' => 'demo',
    ]);
});
