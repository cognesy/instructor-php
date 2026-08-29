<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Creation\BundledReasoning;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Polyglot\Inference\Reasoning\UnsupportedReasoningTranslator;

it('maps evidence-backed provider wire families', function (
    object $translator,
    string $model,
    ReasoningSelection $selection,
    array $expected,
) {
    expect($translator->translate($model, $selection)->options->toArray())->toBe($expected);
})->with([
    'OpenAI Chat' => [
        BundledReasoning::openAiChat(),
        'gpt-5.6',
        ReasoningSelection::effort(ReasoningEffort::XHigh),
        ['reasoning_effort' => 'xhigh'],
    ],
    'OpenAI Responses' => [
        BundledReasoning::openAiResponses(),
        'gpt-5.6',
        ReasoningSelection::effort(ReasoningEffort::Max),
        ['reasoning' => ['effort' => 'max']],
    ],
    'Anthropic adaptive' => [
        BundledReasoning::anthropic(),
        'claude-sonnet-4-6',
        ReasoningSelection::adaptive(ReasoningEffort::High),
        [
            'thinking' => ['type' => 'adaptive'],
            'output_config' => ['effort' => 'high'],
        ],
    ],
    'Anthropic budget' => [
        BundledReasoning::anthropic(),
        'claude-sonnet-4-6',
        ReasoningSelection::budget(1024),
        ['thinking' => ['type' => 'enabled', 'budget_tokens' => 1024]],
    ],
    'Gemini native' => [
        BundledReasoning::gemini(),
        'gemini-3.1-pro-preview',
        ReasoningSelection::effort(ReasoningEffort::High),
        ['generationConfig' => ['thinkingConfig' => ['thinkingLevel' => 'HIGH']]],
    ],
    'GLM boolean' => [
        BundledReasoning::glm(),
        'glm-4.7',
        ReasoningSelection::disabled(),
        ['thinking' => false],
    ],
    'Qwen budget' => [
        BundledReasoning::qwen(),
        'qwen3.8-max',
        ReasoningSelection::budget(1024),
        ['enable_thinking' => true, 'thinking_budget' => 1024],
    ],
    'Cohere budget' => [
        BundledReasoning::cohere(),
        'command-a-reasoning-08-2025',
        ReasoningSelection::budget(64),
        ['thinking' => ['type' => 'enabled', 'token_budget' => 64]],
    ],
    'Mistral subset' => [
        BundledReasoning::mistral(),
        'magistral-medium-latest',
        ReasoningSelection::effort(ReasoningEffort::High),
        ['reasoning_effort' => 'high'],
    ],
    'Kimi toggle' => [
        BundledReasoning::moonshot(),
        'kimi-k2.5',
        ReasoningSelection::enabled(),
        ['thinking' => true],
    ],
    'Grok effort' => [
        BundledReasoning::xai(),
        'grok-4.6',
        ReasoningSelection::effort(ReasoningEffort::Medium),
        ['reasoning_effort' => 'medium'],
    ],
    'OpenRouter budget' => [
        BundledReasoning::openRouter(),
        'openai/gpt-oss-120b',
        ReasoningSelection::budget(2048),
        ['reasoning' => ['max_tokens' => 2048]],
    ],
]);

it('rejects lossy aliases, unsupported values, and unknown models locally', function () {
    expect(fn () => BundledReasoning::deepSeek()->translate(
        'deepseek-v4-pro',
        ReasoningSelection::effort(ReasoningEffort::Medium),
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => BundledReasoning::xai()->translate(
            'grok-4.6',
            ReasoningSelection::disabled(),
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => BundledReasoning::openAiChat()->translate(
            'future-model',
            ReasoningSelection::effort(ReasoningEffort::Low),
        ))->toThrow(InvalidArgumentException::class);
});

it('injects native Gemini controls after its formatter', function () {
    $config = new LLMConfig(model: 'gemini-3.1-pro-preview');
    $body = new ReasoningBodyFormat(
        new GeminiBodyFormat($config, new GeminiMessageFormat),
        BundledReasoning::gemini(),
        $config->model,
    );
    $request = new InferenceRequest(
        messages: Messages::fromString('Think.'),
        reasoning: ReasoningSelection::effort(ReasoningEffort::High),
    );

    expect($body->toRequestBody($request))
        ->toHaveKey('generationConfig.thinkingConfig.thinkingLevel', 'HIGH');
});

it('rejects typed and raw reasoning configuration conflicts', function () {
    $config = new LLMConfig(model: 'gemini-3.1-pro-preview');
    $body = new ReasoningBodyFormat(
        new GeminiBodyFormat($config, new GeminiMessageFormat),
        BundledReasoning::gemini(),
        $config->model,
    );
    $request = new InferenceRequest(
        options: ['thinkingConfig' => ['thinkingLevel' => 'LOW']],
        reasoning: ReasoningSelection::effort(ReasoningEffort::High),
    );

    expect(fn () => $body->toRequestBody($request))->toThrow(InvalidArgumentException::class);
});

it('exposes structured capabilities through the bundled registry', function () {
    $known = BundledInferenceDrivers::capabilities('deepseek', 'deepseek-v4-pro');
    $unknown = BundledInferenceDrivers::capabilities('openai-compatible', 'custom-model');

    expect($known?->reasoning()->known)->toBeTrue()
        ->and($known?->supportsReasoningEffort())->toBeTrue()
        ->and($unknown?->reasoning()->known)->toBeFalse()
        ->and($unknown?->supportsReasoningEffort())->toBeFalse();
});

it('allows provider default through an unknown translator', function () {
    expect((new UnsupportedReasoningTranslator)->translate(
        'custom-model',
        ReasoningSelection::providerDefault(),
    )->options->toArray())->toBe([]);
});
