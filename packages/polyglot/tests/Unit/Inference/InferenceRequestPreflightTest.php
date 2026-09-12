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

it('rejects lossy JSON Schema fallback by default', function () {
    $request = preflightRequest(
        ['jsonSchema' => 'unsupported', 'jsonObject' => 'supported'],
        (new InferenceRequest())->withResponseFormat(ResponseFormat::jsonSchema(['type' => 'object'])),
    );

    expect(fn () => (new InferenceRequestPreflight())->apply($request))
        ->toThrow(InvalidArgumentException::class, 'llm.allow_lossy_fallback is false');
});

it('applies and records explicitly enabled JSON Schema fallback', function () {
    $request = preflightRequest(
        ['jsonSchema' => 'unsupported', 'jsonObject' => 'supported'],
        (new InferenceRequest())->withResponseFormat(ResponseFormat::jsonSchema(['type' => 'object'])),
    );

    $result = (new InferenceRequestPreflight(allowLossyFallback: true))->apply($request);

    expect($result->responseFormat()->toArray())->toBe(['type' => 'json_object'])
        ->and($result->adjustments()->toArray())->toBe([[
            'feature' => 'response_format',
            'requested' => 'json_schema',
            'effective' => 'json_object',
            'reason' => 'The exact offering does not support JSON Schema.',
        ]]);
});

it('rejects a non-text response format when the exact offering forbids it with tools', function (
    InferenceRequest $request,
) {
    $request = preflightRequest(
        ['tools' => 'supported', 'responseFormatWithTools' => 'unsupported'],
        $request,
    );

    expect(fn () => (new InferenceRequestPreflight())->apply($request))
        ->toThrow(InvalidArgumentException::class, 'response format with tools');
})->with([
    'request response format' => new InferenceRequest(
        tools: preflightTools(),
        responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
    ),
    'cached response format' => new InferenceRequest(
        tools: preflightTools(),
        cachedContext: new CachedInferenceContext(
            responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
        ),
    ),
    'cached tools and duplicated response format' => new InferenceRequest(
        responseFormat: ResponseFormat::jsonObject(),
        cachedContext: new CachedInferenceContext(
            tools: preflightTools(),
            responseFormat: ResponseFormat::jsonObject(),
        ),
    ),
]);

it('preserves tools with a non-text response format unless the combination is explicitly unsupported', function (
    array $capabilities,
) {
    $request = preflightRequest(
        $capabilities,
        new InferenceRequest(
            tools: preflightTools(),
            responseFormat: ResponseFormat::jsonObject(),
        ),
    );

    $result = (new InferenceRequestPreflight())->apply($request);

    expect($result->hasTools())->toBeTrue()
        ->and($result->responseFormat())->toEqual(ResponseFormat::jsonObject());
})->with([
    'supported' => [['responseFormatWithTools' => 'supported']],
    'unknown' => [[]],
]);

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

it('treats unknown reasoning as no local assertion and rejects lossy mappings by default', function () {
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

    expect((new InferenceRequestPreflight())->apply($unknown))->toBe($unknown)
        ->and(fn () => (new InferenceRequestPreflight())->apply($lossy))
        ->toThrow(InvalidArgumentException::class, 'llm.allow_lossy_fallback is false');
});

it('records explicitly enabled lossy reasoning mappings', function () {
    $request = preflightRequest(
        ['reasoning' => [
            'selections' => ['effort'],
            'efforts' => [[
                'requested' => 'medium',
                'provider' => 'high',
                'effective' => 'high',
                'quality' => 'lossy',
            ]],
        ]],
        (new InferenceRequest())->withReasoning(ReasoningSelection::effort(ReasoningEffort::Medium)),
    );

    $prepared = (new InferenceRequestPreflight(allowLossyFallback: true))->apply($request);

    expect($prepared->adjustments()->toArray())->toBe([[
        'feature' => 'reasoning',
        'requested' => 'effort:medium',
        'effective' => 'effort:high',
        'reason' => 'The supplied reasoning effort mapping is lossy.',
    ]]);
});
