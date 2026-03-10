<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

class GeneratedEventUser
{
    public string $name;
    public int $age;
}

it('dispatches StructuredOutputResponseGenerated with StructuredOutputResponse for sync execution', function () {
    $events = new EventDispatcher();
    $generatedResponse = null;

    $events->addListener(StructuredOutputResponseGenerated::class, function (StructuredOutputResponseGenerated $event) use (&$generatedResponse): void {
        $generatedResponse = $event->data['response'] ?? null;
    });

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: new FakeInferenceDriver([
            new InferenceResponse(content: '{"name":"Ava","age":34}'),
        ]),
        events: $events,
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'Extract user',
            responseModel: GeneratedEventUser::class,
        )
        ->create();

    $response = $pending->response();

    expect($response)->toBeInstanceOf(StructuredOutputResponse::class);
    expect($generatedResponse)->toBeInstanceOf(StructuredOutputResponse::class);
    expect($generatedResponse->isFinal())->toBeTrue();
    expect($generatedResponse->value())->toBeInstanceOf(GeneratedEventUser::class);
    expect($generatedResponse->content())->toBe('{"name":"Ava","age":34}');
});
