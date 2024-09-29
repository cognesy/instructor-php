<?php
namespace Tests\Feature;

use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Instructor;
use Tests\Examples\Instructor\EventSink;
use Tests\Examples\Instructor\Person;
use Tests\MockLLM;
use Throwable;

$mockLLM = MockLLM::get([
    '{"name":"Jason","age":28}'
]);
$text = "His name is Jason, he is 28 years old.";

it('handles direct call', function () use ($mockLLM, $text) {
    $instructor = (new Instructor)->withDriver($mockLLM);
    $person = $instructor->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});

it('handles onEvent()', function () use ($mockLLM, $text) {
    $events = new EventSink();
    $instructor = (new Instructor)->withDriver($mockLLM);
    $person = $instructor->onEvent(RequestReceived::class, fn($e) => $events->onEvent($e))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBe(1);
});

it('handles wiretap()', function () use ($mockLLM, $text) {
    $events = new EventSink();
    $instructor = (new Instructor)->withDriver($mockLLM);
    $person = $instructor->wiretap(fn($e) => $events->onEvent($e))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBeGreaterThan(1);
});

it('handles onError()', function () use ($mockLLM, $text) {
    $events = new EventSink();
    $mockLLM = MockLLM::get(['']); // mock empty response - should cause deserialization error
    $instructor = (new Instructor)->withDriver($mockLLM);
    $person = $instructor->onError(fn($e) => $events->onEvent($e))->respond(
        messages: '',
        responseModel: Person::class,
    );
    expect($events->count())->toBe(1);
});

it('throws exception if no onError() provided', function () use ($mockLLM) {
    $thrownException = false;
    $mockLLM = MockLLM::get(['']); // mock empty response - should cause deserialization error
    try {
        $instructor = (new Instructor)->withDriver($mockLLM);
        $person = $instructor->respond(
            messages: '',
            responseModel: Person::class,
        );
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(Throwable::class);
        $thrownException = true;
    }
    expect($thrownException)->toBeTrue();
});
