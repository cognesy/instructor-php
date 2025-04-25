<?php

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\MockLLM;

it('accepts string as input', function () {
    $mockLLM = MockLLM::get(['{"name":"Jason","age":28}']);

    $person = (new Instructor)->withHttpClient($mockLLM)->respond(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Person::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});


it('self-corrects values extracted by LLM based on validation results', function () {
    $mockLLM = MockLLM::get([
        '{"name": "JX", "age": -28}',
        '{"name": "Jason", "age": 28}'
    ]);

    $text = "His name is JX, aka Jason, is -28 years old.";
    $person = (new Instructor)->withHttpClient($mockLLM)->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
        maxRetries: 2,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});
