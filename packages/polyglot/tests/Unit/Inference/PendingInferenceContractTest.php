<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('is lazy until response data is accessed and memoizes the finalized raw response', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('hello world')),
    ]);

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Say hello'))
        ->create();

    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    expect($driver->responseCalls)->toBe(0);

    $firstResponse = $pending->response();
    $secondResponse = $pending->response();
    $message = $pending->get();

    expect($driver->responseCalls)->toBe(1);
    expect($firstResponse)->toBe($secondResponse);
    expect($message)->toBe($firstResponse->message());
    expect($message->content()->toString())->toBe('hello world');
    expect($firstResponse->message()->content()->toString())->toBe('hello world');
});
