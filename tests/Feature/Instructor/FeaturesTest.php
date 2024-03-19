<?php
namespace Tests;

use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenAI\ToolsMode\OpenAIToolCaller;
use Tests\Examples\Extraction\Person;

it('accepts string as input', function () {
    $mockLLM = MockLLM::get(['{"name":"Jason","age":28}']);

    $person = (new Instructor)->withConfig([OpenAIToolCaller::class => $mockLLM])->respond(
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
    $person = (new Instructor)->withConfig([OpenAIToolCaller::class => $mockLLM])->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
        maxRetries: 2,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});

it('supports streaming', function () {
    //$mockLLM = MockLLM::get(['{"name":"Jason","age":28}']);

    $person = (new Instructor)
        ->wiretap(fn($e)=>dump($e->toConsole()))
        ->onEvent(ResponseReceivedFromLLM::class, fn($e)=>dump($e->response))
        ->onError(fn($e)=>dump($e->error))
        ->respond(
            messages: "His name is Jason, he is 28 years old.",
            responseModel: Person::class,
            options: ['stream' => true],
        )
    ;
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
})->skip("Streaming is not implemented yet");
