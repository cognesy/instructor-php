<?php

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\MockLLM;

it('supports simple properties', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28}',
    ]);

    $text = "His name is Jason, he is 28 years old.";
    $person = (new Instructor)->withHttpClient($mockLLM)->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
})->skip("Maybe adapter not implemented yet");
