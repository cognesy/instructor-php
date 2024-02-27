<?php
namespace Tests;

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Validator;
use Cognesy\Instructor\LLM;
use Tests\Examples\Person;
use Mockery;


it('validates using attribute rules', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = 28;
    $validator = new Validator();
    expect($validator->validate($person))->toBe(true);
});


it('finds validation error', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = -28;
    $validator = new Validator();
    expect($validator->validate($person))->toBe(false);
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
