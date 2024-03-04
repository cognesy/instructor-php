<?php

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenAI\LLM;
use Tests\Examples\Extraction\Person;


it('supports simple properties', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name":"Jason","age":28}',
    );

    $text = "His name is Jason, he is 28 years old.";
    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});
