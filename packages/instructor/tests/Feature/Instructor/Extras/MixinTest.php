<?php

use Cognesy\Instructor\Tests\Examples\Mixin\PersonWithMixin;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Polyglot\LLM\LLMProvider;

it('supports HandlesExtraction mixin', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jason","age":28}'
    ]);

    $customLLM = LLMProvider::new()->withHttpClient($mockHttp);
    $person = PersonWithMixin::infer(
        messages: "His name is Jason, he is 28 years old.",
        llm: $customLLM
    );

    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithMixin::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});
