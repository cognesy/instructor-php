<?php
namespace Tests\Feature;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseGenerated;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallCompleted;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallStarted;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallUpdated;
use Cognesy\Instructor\Events\LLM\StreamedResponseFinished;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallRequested;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseConvertedToObject;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResponseReceived;
use Cognesy\Instructor\Events\RequestHandler\FunctionCallResultReady;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ResponseGenerationFailed;
use Cognesy\Instructor\Events\RequestHandler\ResponseModelBuilt;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseTransformed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidated;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationFailed;
use Cognesy\Instructor\Extras\Scalars\Scalar;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenAI\ToolsMode\OpenAIToolCaller;
use Tests\Examples\Extraction\Person;
use Tests\Examples\Instructor\EventSink;
use Tests\MockLLM;

$isMock = true;
$text = "His name is J, he is 28 years old. J is also known as Jason.";

it('handles events for simple case w/reattempt on validation - success', function ($event) use ($isMock, $text) {
    $mockLLM = !$isMock ? null : MockLLM::get([
        '{"name": "Jason", "age":-28}',
        '{"name": "Jason", "age":28}',
    ]);
    $events = new EventSink();
    $person = (new Instructor)->onEvent($event, fn($e) => $events->onEvent($e))
        //->wiretap(fn($e) => dump($e))
        ->withConfig([OpenAIToolCaller::class => $mockLLM])
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
    //    [InstructorStarted::class],
    //    [InstructorReady::class],
    [RequestReceived::class],
    [ResponseGenerated::class],
    // RequestHandler
    [FunctionCallRequested::class],
    [FunctionCallResponseConvertedToObject::class],
    [FunctionCallResponseReceived::class],
    //[FunctionCallResultReady::class],
    [NewValidationRecoveryAttempt::class],
    //[ResponseGenerationFailed::class],
    [ResponseModelBuilt::class],
    //[ValidationRecoveryLimitReached::class],
    // LLM
    //[ChunkReceived::class],
    //[PartialJsonReceived::class],
    //[RequestSentToLLM::class],
    //[ResponseReceivedFromLLM::class],
    //[StreamedFunctionCallCompleted::class],
    //[StreamedFunctionCallStarted::class],
    //[StreamedFunctionCallUpdated::class],
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


it('handles events for simple case - validation failure', function ($event) use ($isMock, $text) {
    $mockLLM = !$isMock ? null : MockLLM::get([
        '{"name": "J", "age":-28}',
        '{"name": "J", "age":-28}',
    ]);
    $events = new EventSink();
    $person = (new Instructor)->onEvent($event, fn($e) => $events->onEvent($e))
        ->withConfig([OpenAIToolCaller::class => $mockLLM])
        ->onError(fn($e) => $events->onEvent($e))
        ->respond(
            messages: [['role' => 'user', 'content' => $text]],
            responseModel: Person::class,
            maxRetries: 0,
        );
    expect($person)->toBeNull();
    expect($events->count())->toBeGreaterThan(0);
    expect($events->first())->toBeInstanceOf($event);
    expect((string) $events->first())->toBeString()->not()->toBeEmpty();
})->with([
    // Instructor
    //    [InstructorStarted::class],
    //    [InstructorReady::class],
    [RequestReceived::class],
    //[ResponseReturned::class],
    // RequestHandler
    [FunctionCallRequested::class],
    //[FunctionCallResponseConvertedToObject::class],
    [FunctionCallResponseReceived::class],
    //[FunctionCallResultReady::class],
    //[NewValidationRecoveryAttempt::class],
    [ResponseGenerationFailed::class],
    [ResponseModelBuilt::class],
    [ValidationRecoveryLimitReached::class],
    // LLM
    //[ChunkReceived::class],
    //[PartialJsonReceived::class],
    //[RequestSentToLLM::class],
    //[ResponseReceivedFromLLM::class],
    //[StreamedFunctionCallCompleted::class],
    //[StreamedFunctionCallStarted::class],
    //[StreamedFunctionCallUpdated::class],
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
    [ErrorRaised::class],
]);

it('handles events for custom case', function ($event) use ($isMock, $text) {
    $mockLLM = !$isMock ? null : MockLLM::get([
        '{"age":28}'
    ]);
    $events = new EventSink();
    $age = (new Instructor)->onEvent($event, fn($e) => $events->onEvent($e))
        ->withConfig([OpenAIToolCaller::class => $mockLLM])
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
    [FunctionCallRequested::class],
    [FunctionCallResponseConvertedToObject::class],
    [FunctionCallResponseReceived::class],
    //[FunctionCallResultReady::class],
    //[NewValidationRecoveryAttempt::class],
    //[ResponseGenerationFailed::class],
    [ResponseModelBuilt::class],
    // [ValidationRecoveryLimitReached::class],
    // ==== LLM
    //[ChunkReceived::class],
    //[PartialJsonReceived::class],
    //[RequestSentToLLM::class],
    //[ResponseReceivedFromLLM::class],
    //[StreamedFunctionCallCompleted::class],
    //[StreamedFunctionCallStarted::class],
    //[StreamedFunctionCallUpdated::class],
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

