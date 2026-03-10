<?php declare(strict_types=1);

use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Messages\Messages;

final class StructuredOutputFakePerson
{
    public function __construct(
        public string $name = 'John',
    ) {}
}

it('normalizes ergonomic structured output fake messages to typed messages', function () {
    $fake = new StructuredOutputFake([
        StructuredOutputFakePerson::class => new StructuredOutputFakePerson(),
    ]);

    $fake->with(
        messages: 'Extract person',
        responseModel: StructuredOutputFakePerson::class,
    )->get();

    $recorded = $fake->recorded();

    expect($recorded)->toHaveCount(1);
    expect($recorded[0]['messages'])->toBeInstanceOf(Messages::class);
    expect($recorded[0]['messages']->toString())->toContain('Extract person');
});
