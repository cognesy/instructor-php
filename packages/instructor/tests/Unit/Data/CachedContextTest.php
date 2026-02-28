<?php declare(strict_types=1);

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Extras\Example\Example;

it('round-trips cached context examples through array serialization', function () {
    $context = new CachedContext(
        messages: [['role' => 'user', 'content' => 'Tell me a joke.']],
        system: 'You are concise.',
        prompt: 'Respond in one sentence.',
        examples: [new Example(
            input: 'Tell me about ducks.',
            output: ['answer' => 'Ducks are waterfowl.'],
        )],
    );

    $serialized = $context->toArray();

    expect($serialized['examples'][0])->toBe([
        'input' => 'Tell me about ducks.',
        'output' => ['answer' => 'Ducks are waterfowl.'],
        'structured' => true,
        'template' => '',
    ]);

    $restored = CachedContext::fromArray($serialized);

    expect($restored->examples())->toHaveCount(1);
    expect($restored->examples()[0])->toBeInstanceOf(Example::class);
    expect($restored->examples()[0]->input())->toBe('Tell me about ducks.');
    expect($restored->examples()[0]->output())->toBe(['answer' => 'Ducks are waterfowl.']);
});
