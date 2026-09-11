<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Core\InferenceRequestPreflight;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

function preflightRequest(array $capabilities, ?InferenceRequest $request = null): InferenceRequest {
    $catalog = ModelCatalog::fromArray([
        'version' => 'preflight-v1',
        'models' => [[
            'driver' => 'test',
            'model' => 'exact-model',
            'capabilities' => $capabilities,
        ]],
    ]);

    return ($request ?? new InferenceRequest())
        ->withModel('exact-model')
        ->withModelProfile($catalog->find('test', 'exact-model'));
}

function preflightTools(): ToolDefinitions {
    return ToolDefinitions::fromArray([[
        'type' => 'function',
        'function' => [
            'name' => 'lookup',
            'description' => 'Look up a value',
            'parameters' => ['type' => 'object', 'properties' => []],
        ],
    ]]);
}

it('rejects capabilities explicitly marked unsupported', function (
    array $capabilities,
    InferenceRequest $request,
    string $message,
) {
    $request = preflightRequest($capabilities, $request);

    expect(fn () => (new InferenceRequestPreflight())->apply($request))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'streaming' => [['streaming' => 'unsupported'], (new InferenceRequest())->withStreaming(true), 'streaming'],
    'tools' => [['tools' => 'unsupported'], (new InferenceRequest())->withTools(preflightTools()), 'tools'],
    'tool choice' => [['toolChoice' => 'unsupported'], (new InferenceRequest())->withToolChoice(ToolChoice::auto()), 'tool choice'],
    'JSON Object' => [['jsonObject' => 'unsupported'], (new InferenceRequest())->withResponseFormat(ResponseFormat::jsonObject()), 'JSON Object'],
]);

it('degrades JSON Schema only through an explicit JSON Object capability', function () {
    $request = preflightRequest(
        ['jsonSchema' => 'unsupported', 'jsonObject' => 'supported'],
        (new InferenceRequest())->withResponseFormat(ResponseFormat::jsonSchema(['type' => 'object'])),
    );

    $result = (new InferenceRequestPreflight())->apply($request);

    expect($result->responseFormat()->toArray())->toBe(['type' => 'json_object']);
});

it('removes a non-text response format when the exact offering forbids it with tools', function () {
    $request = preflightRequest(
        ['tools' => 'supported', 'responseFormatWithTools' => 'unsupported'],
        new InferenceRequest(
            tools: preflightTools(),
            cachedContext: new CachedInferenceContext(
                responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
            ),
        ),
    );

    $result = (new InferenceRequestPreflight())->apply($request);

    expect($result->hasResponseFormat())->toBeFalse();
});

it('uses the exact profile to validate reasoning and leaves unknown ordinary facts executable', function () {
    $known = preflightRequest(
        ['reasoning' => [
            'selections' => ['effort'],
            'efforts' => [['requested' => 'high', 'provider' => 'high']],
        ]],
        (new InferenceRequest())->withReasoning(ReasoningSelection::effort(ReasoningEffort::High)),
    );
    $unknown = preflightRequest([], (new InferenceRequest())->withStreaming(true));

    expect((new InferenceRequestPreflight())->apply($known))->toBeInstanceOf(InferenceRequest::class)
        ->and((new InferenceRequestPreflight())->apply($unknown))->toBeInstanceOf(InferenceRequest::class);
});

it('rejects unknown or lossy typed reasoning selections', function () {
    $unknown = preflightRequest(
        [],
        (new InferenceRequest())->withReasoning(ReasoningSelection::enabled()),
    );
    $lossy = preflightRequest(
        ['reasoning' => [
            'selections' => ['effort'],
            'efforts' => [[
                'requested' => 'medium',
                'provider' => 'medium',
                'effective' => 'high',
                'quality' => 'lossy',
            ]],
        ]],
        (new InferenceRequest())->withReasoning(ReasoningSelection::effort(ReasoningEffort::Medium)),
    );

    expect(fn () => (new InferenceRequestPreflight())->apply($unknown))
        ->toThrow(InvalidArgumentException::class, 'reasoning selection')
        ->and(fn () => (new InferenceRequestPreflight())->apply($lossy))
        ->toThrow(InvalidArgumentException::class, 'reasoning selection');
});
