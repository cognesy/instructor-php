<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Instructor;
use Tests\Examples\Mixin\PersonWithMixin;
use Tests\MockLLM;

it('supports HandlesExtraction mixin', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28}'
    ]);

    $instructor = (new Instructor)->withHttpClient($mockLLM);
    $person = PersonWithMixin::infer(
        messages: "His name is Jason, he is 28 years old.",
        instructor: $instructor
    );

    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithMixin::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});
