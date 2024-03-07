<?php

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Instructor;
use Tests\Examples\Extraction\Person;
use Tests\MockLLM;


it('supports simple properties', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28}',
    ]);

    $text = "His name is Jason, he is 28 years old.";
    $person = (new Instructor)->withOverride([CanCallFunction::class => $mockLLM])->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
})->skip("Not implemented yet");
