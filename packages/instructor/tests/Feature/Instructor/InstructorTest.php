<?php

use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Instructor\EventSink;
use Cognesy\Instructor\Tests\Examples\Instructor\Person;
use Cognesy\Instructor\Tests\MockLLM;

$mockLLM = MockLLM::get([
    '{"name":"Jason","age":28}'
]);
$text = "His name is Jason, he is 28 years old.";

it('handles direct call', function () use ($mockLLM, $text) {
    $structuredOutput = (new StructuredOutput)->withHttpClient($mockLLM);
    $person = $structuredOutput->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )->get();
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});

it('handles onEvent()', function () use ($mockLLM, $text) {
    $events = new EventSink();
    $structuredOutput = (new StructuredOutput)->withHttpClient($mockLLM);
    $person = $structuredOutput->onEvent(RequestReceived::class, fn($e) => $events->onEvent($e))->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )->get();
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBe(1);
});

it('handles wiretap()', function () use ($mockLLM, $text) {
    $events = new EventSink();
    $structuredOutput = (new StructuredOutput)->withHttpClient($mockLLM);
    $person = $structuredOutput->wiretap(fn($e) => $events->onEvent($e))->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )->get();
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBeGreaterThan(1);
});
