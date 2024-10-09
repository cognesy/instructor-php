<?php
namespace Tests\Feature;

//use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
use Cognesy\Instructor\Events\Response\ResponseTransformed;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Instructor;
use Tests\Examples\Extraction\Person;
use Tests\Examples\Instructor\EventSink;
use Tests\MockLLM;

$text = "His name is J, he is 28 years old. J is also known as Jason.";

it('handles events for simple case w/reattempt on validation - success', function ($event) use ($text) {
    $mockLLM = MockLLM::get([
        '{"name": "Jason", "age":-28}',
        '{"name": "Jason", "age":28}',
    ]);
    $events = new EventSink();
    $person = (new Instructor)->withHttpClient($mockLLM)
        ->onEvent($event, fn($e) => $events->onEvent($e))
        //->wiretap(fn($e) => dump($e))
        ->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
        maxRetries: 2,
    );
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($events->count())->toBeGreaterThan(0);
    expect($events->first())->toBeInstanceOf($event);
    expect((string) $events->first())->toBeString()->not()->toBeEmpty();
})->with([
    // Instructor
    //[InstructorStarted::class],
    //[InstructorReady::class],
    [RequestReceived::class],
    [ResponseGenerated::class],
    // RequestHandler
    [NewValidationRecoveryAttempt::class],
    //[ResponseGenerationFailed::class],
    [ResponseModelBuilt::class],
    //[ValidationRecoveryLimitReached::class],
    // LLM
    //[ChunkReceived::class],
    //[PartialJsonReceived::class],
    //[RequestSentToLLM::class],
    //[ResponseReceivedFromLLM::class],
    //[StreamedToolCallCompleted::class],
    //[StreamedToolCallStarted::class],
    //[StreamedToolCallUpdated::class],
    //[StreamedResponseFinished::class],
    //[StreamedResponseReceived::class],
    // ResponseHandler
    //[CustomResponseDeserializationAttempt::class],
    //[CustomResponseValidationAttempt::class],
    [ResponseDeserializationAttempt::class],
    //[ResponseDeserializationFailed::class],
    //[ResponseTransformed::class],
    [ResponseValidated::class],
    [ResponseValidationAttempt::class],
    //[ResponseValidationFailed::class]
]);


it('handles events for simple case - validation failure', function ($event) use ($text) {
    $mockLLM = MockLLM::get([
        '{"name": "J", "age":-28}',
        '{"name": "J", "age":-28}',
    ]);
    $events = new EventSink();

    // expect exception
    $this->expectException(\Exception::class);
    $person = (new Instructor)->withHttpClient($mockLLM)
        ->onEvent($event, fn($e) => $events->onEvent($e))
        ->respond(
            messages: [['role' => 'user', 'content' => $text]],
            responseModel: Person::class,
            maxRetries: 1,
        );

    expect($person)->toBeNull();
    expect($events->count())->toBeGreaterThan(0);
    expect($events->first())->toBeInstanceOf($event);
    expect((string) $events->first())->toBeString()->not()->toBeEmpty();
})->with([
    // Instructor
    //[InstructorStarted::class],
    //[InstructorReady::class],
    [RequestReceived::class],
    //[ResponseReturned::class],
    // RequestHandler
    //[NewValidationRecoveryAttempt::class],
    [ResponseGenerationFailed::class],
    [ResponseModelBuilt::class],
    [ValidationRecoveryLimitReached::class],
    // LLM
    //[ChunkReceived::class],
    //[PartialJsonReceived::class],
    //[RequestSentToLLM::class],
    //[ResponseReceivedFromLLM::class],
    //[StreamedToolCallCompleted::class],
    //[StreamedToolCallStarted::class],
    //[StreamedToolCallUpdated::class],
    //[StreamedResponseFinished::class],
    //[StreamedResponseReceived::class],
    // ResponseHandler
    //[CustomResponseDeserializationAttempt::class],
    //[CustomResponseValidationAttempt::class],
    [ResponseDeserializationAttempt::class],
    //[ResponseDeserializationFailed::class],
    //[ResponseTransformed::class],
    //[ResponseValidated::class],
    [ResponseValidationAttempt::class],
    [ResponseValidationFailed::class],
    //[ErrorRaised::class],
]);

it('handles events for custom case', function ($event) use ($text) {
    $mockLLM = MockLLM::get([
        '{"age":28}'
    ]);
    $events = new EventSink();
    $age = (new Instructor)->withHttpClient($mockLLM)
        ->onEvent($event, fn($e) => $events->onEvent($e))
        ->respond(
            messages: [['role' => 'user', 'content' => $text]],
            responseModel: Scalar::integer('age'),
        );
    expect($age)->toBe(28);
    expect($events->count())->toBe(1);
    expect($events->first())->toBeInstanceOf($event);
    expect((string) $events->first())->toBeString()->not()->toBeEmpty();
})->with([
    // ==== Instructor
    //    [InstructorStarted::class],
    //    [InstructorReady::class],
    [RequestReceived::class],
    [ResponseGenerated::class],
    // ==== RequestHandler
    //[NewValidationRecoveryAttempt::class],
    //[ResponseGenerationFailed::class],
    [ResponseModelBuilt::class],
    // [ValidationRecoveryLimitReached::class],
    // ==== LLM
    //[ChunkReceived::class],
    //[PartialJsonReceived::class],
    //[RequestSentToLLM::class],
    //[ResponseReceivedFromLLM::class],
    //[StreamedToolCallCompleted::class],
    //[StreamedToolCallStarted::class],
    //[StreamedToolCallUpdated::class],
    //[StreamedResponseFinished::class],
    //[StreamedResponseReceived::class],
    // ==== ResponseHandler
    [CustomResponseDeserializationAttempt::class],
    [CustomResponseValidationAttempt::class],
    //[ResponseDeserializationAttempt::class],
    //[ResponseDeserializationFailed::class],
    [ResponseTransformed::class],
    [ResponseValidated::class],
    //[ResponseValidationAttempt::class],
    //[ResponseValidationFailed::class]
]);
