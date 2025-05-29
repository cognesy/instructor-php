<?php

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\MockHttp;

it('supports simple properties', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jason","age":28}',
    ]);

    $text = "His name is Jason, he is 28 years old.";
    $person = (new StructuredOutput)->withHttpClient($mockHttp)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )->get();
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
})->skip("Maybe adapter not implemented yet");
