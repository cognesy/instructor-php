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

it('dispatches StructuredOutputResponseGenerated with normalized payload for sync execution', function () {
    $events = new EventDispatcher();
    $generatedPayload = null;

    $events->addListener(StructuredOutputResponseGenerated::class, function (StructuredOutputResponseGenerated $event) use (&$generatedPayload): void {
        $generatedPayload = $event->data;
    });

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: new FakeInferenceDriver([
            new InferenceResponse(content: '{"name":"Ava","age":34}', finishReason: 'stop'),
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
    expect($generatedPayload)->toBeArray();
    expect($generatedPayload)->toHaveKeys([
        'requestId',
        'executionId',
        'attemptId',
        'phase',
        'phaseId',
        'isPartial',
        'hasValue',
        'valueType',
        'value',
        'finishReason',
        'content',
        'contentLength',
        'reasoningContent',
        'toolArgsSnapshot',
        'toolCalls',
    ]);
    expect($generatedPayload['phase'])->toBe('response.generated');
    expect($generatedPayload['phaseId'])->toContain($generatedPayload['executionId']);
    expect($generatedPayload['phaseId'])->toContain($generatedPayload['attemptId']);
    expect($generatedPayload['isPartial'])->toBeFalse();
    expect($generatedPayload['hasValue'])->toBeTrue();
    expect($generatedPayload['valueType'])->toBe(GeneratedEventUser::class);
    expect($generatedPayload['value'])->toBe([
        'name' => 'Ava',
        'age' => 34,
    ]);
    expect($generatedPayload['finishReason'])->toBe('stop');
    expect($generatedPayload['content'])->toBe('{"name":"Ava","age":34}');
    expect($generatedPayload['contentLength'])->toBe(strlen('{"name":"Ava","age":34}'));
    expect($generatedPayload['reasoningContent'])->toBe('');
    expect($generatedPayload['toolArgsSnapshot'])->toBe('');
    expect($generatedPayload['toolCalls'])->toBe([]);
    expect($generatedPayload)->not()->toHaveKey('response');
});
