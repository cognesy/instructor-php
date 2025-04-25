<?php

use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Instructor\Tests\Examples\Mixin\PersonWithMixin;
use Cognesy\Instructor\Tests\MockLLM;

it('supports HandlesExtraction mixin', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28}'
    ]);

    $customLLM = (new LLM)->withHttpClient($mockLLM);
    $person = PersonWithMixin::infer(
        messages: "His name is Jason, he is 28 years old.",
        llm: $customLLM
    );

    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithMixin::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});
