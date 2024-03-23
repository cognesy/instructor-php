<?php
namespace Tests\Feature;

use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenAI\ToolsMode\OpenAIToolCaller;
use Tests\Examples\Instructor\EventSink;
use Tests\Examples\Instructor\Person;
use Tests\MockLLM;

$isMock = true;
$mockLLM = !$isMock ? null : MockLLM::get([
    '{"name":"Jason","age":28}'
]);
$instructor = (new Instructor)->withConfig([OpenAIToolCaller::class => $mockLLM]);
$text = "His name is Jason, he is 28 years old.";

it('handles direct call', function () use ($instructor, $text) {
    $person = $instructor->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});

it('handles onEvent()', function () use ($instructor, $text) {
    $events = new EventSink();
    $person = $instructor->onEvent(RequestReceived::class, fn($e) => $events->onEvent($e))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBe(1);
});

it('handles wiretap()', function () use ($instructor, $text) {
    $events = new EventSink();
    $person = $instructor->wiretap(fn($e) => $events->onEvent($e))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBeGreaterThan(1);
});

it('handles onError()', function () use ($instructor, $text) {
    $events = new EventSink();
    $person = $instructor->onError(fn($e) => $events->onEvent($e))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: '',
    );
    expect($events->count())->toBe(1);
});

it('throws exception if no onxError() provided', function () use ($isMock, $text) {
    $isMock = true;
    $mockLLM = !$isMock ? null : MockLLM::get([
        '{"name":"Jason","age":28}'
    ]);
    $instructor = (new Instructor)->withConfig([OpenAIToolCaller::class => $mockLLM]);

    $e = null;
    $thrownException = false;
    try {
        $person = $instructor->respond(
            messages: [['role' => 'user', 'content' => $text]],
            responseModel: '',
        );
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(\Throwable::class);
        $thrownException = true;
    }
    expect($thrownException)->toBeTrue();
});
