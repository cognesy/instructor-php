<?php

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\MockHttp;

it('accepts string as input', function () {
    $mockHttp = MockHttp::get(['{"name":"Jason","age":28}']);

    $person = (new StructuredOutput)->withRuntime(makeStructuredRuntime(httpClient: $mockHttp))->with(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Person::class,
    )->get();
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});


it('self-corrects values extracted by LLM based on validation results', function () {
    $mockHttp = MockHttp::get([
        '{"name": "JX", "age": -28}',
        '{"name": "Jason", "age": 28}'
    ]);

    $text = "His name is JX, aka Jason, is -28 years old.";
    $person = (new StructuredOutput)->withRuntime(makeStructuredRuntime(httpClient: $mockHttp, maxRetries: 2))->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )->get();
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});
