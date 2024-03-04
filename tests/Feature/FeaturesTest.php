<?php
namespace Tests;

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenAI\LLM;
use Mockery;
use Tests\Examples\Extraction\Person;

it('accepts string as input', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name":"Jason","age":28}',
    );

    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Person::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});


it('self-corrects values extracted by LLM based on validation results', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name": "JX", "age": -28}',
        fn() => '{"name": "Jason", "age": 28}',
    );

    $text = "His name is JX, aka Jason, is -28 years old.";
    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
        maxRetries: 2,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});

