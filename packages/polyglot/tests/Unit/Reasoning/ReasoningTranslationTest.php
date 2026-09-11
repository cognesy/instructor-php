<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Core\InferenceRequestPreflight;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningCapabilities;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use Cognesy\Polyglot\Inference\Reasoning\UnsupportedReasoningTranslator;

it('maps evidence-backed provider wire families from exact offering records', function (
    string $driver,
    string $model,
    ReasoningWireFormat $wireFormat,
    ReasoningSelection $selection,
    array $expected,
) {
    $capabilities = ModelCatalog::bundled()->find($driver, $model)->capabilities->reasoning;
    $request = (new InferenceRequest(model: $model, reasoning: $selection))
        ->withModelProfile(ModelCatalog::bundled()->find($driver, $model));
    (new InferenceRequestPreflight())->apply($request);

    $translation = (new ConfiguredReasoningTranslator($wireFormat))
        ->translate($capabilities, $selection);

    expect($capabilities->known)->toBeTrue()
        ->and($translation->options->toArray())->toBe($expected);
})->with([
    'OpenAI Chat' => ['openai', 'gpt-5.6', ReasoningWireFormat::NamedEffort, ReasoningSelection::effort(ReasoningEffort::XHigh), ['reasoning_effort' => 'xhigh']],
    'OpenAI Responses' => ['openai-responses', 'gpt-5.6', ReasoningWireFormat::OpenResponses, ReasoningSelection::effort(ReasoningEffort::Max), ['reasoning' => ['effort' => 'max']]],
    'Anthropic adaptive' => ['anthropic', 'claude-sonnet-4-6', ReasoningWireFormat::Anthropic, ReasoningSelection::adaptive(ReasoningEffort::High), ['thinking' => ['type' => 'adaptive'], 'output_config' => ['effort' => 'high']]],
    'Anthropic budget' => ['anthropic', 'claude-sonnet-4-6', ReasoningWireFormat::Anthropic, ReasoningSelection::budget(1024), ['thinking' => ['type' => 'enabled', 'budget_tokens' => 1024]]],
    'Gemini native' => ['gemini', 'gemini-3.1-pro-preview', ReasoningWireFormat::Gemini, ReasoningSelection::effort(ReasoningEffort::High), ['generationConfig' => ['thinkingConfig' => ['thinkingLevel' => 'HIGH']]]],
    'GLM boolean' => ['glm', 'glm-4.7', ReasoningWireFormat::BooleanThinking, ReasoningSelection::disabled(), ['thinking' => false]],
    'Qwen budget' => ['qwen', 'qwen3.8-max', ReasoningWireFormat::Qwen, ReasoningSelection::budget(1024), ['enable_thinking' => true, 'thinking_budget' => 1024]],
    'Cohere budget' => ['cohere', 'command-a-reasoning-08-2025', ReasoningWireFormat::Cohere, ReasoningSelection::budget(64), ['thinking' => ['type' => 'enabled', 'token_budget' => 64]]],
    'Mistral subset' => ['mistral', 'magistral-medium-latest', ReasoningWireFormat::NamedEffort, ReasoningSelection::effort(ReasoningEffort::High), ['reasoning_effort' => 'high']],
    'Kimi toggle' => ['moonshot', 'kimi-k2.5', ReasoningWireFormat::BooleanThinking, ReasoningSelection::enabled(), ['thinking' => true]],
    'Grok effort' => ['xai', 'grok-4.6', ReasoningWireFormat::NamedEffort, ReasoningSelection::effort(ReasoningEffort::Medium), ['reasoning_effort' => 'medium']],
    'OpenRouter budget' => ['openrouter', 'openai/gpt-oss-120b', ReasoningWireFormat::OpenRouter, ReasoningSelection::budget(2048), ['reasoning' => ['max_tokens' => 2048]]],
]);

it('rejects lossy aliases, unsupported values, and near-match model names in preflight', function () {
    $catalog = ModelCatalog::bundled();
    $lossy = (new InferenceRequest(
        model: 'deepseek-v4-pro',
        reasoning: ReasoningSelection::effort(ReasoningEffort::Medium),
    ))->withModelProfile($catalog->find('deepseek', 'deepseek-v4-pro'));
    $unsupported = (new InferenceRequest(
        model: 'grok-4.6',
        reasoning: ReasoningSelection::disabled(),
    ))->withModelProfile($catalog->find('xai', 'grok-4.6'));
    $nearMatch = (new InferenceRequest(
        model: 'gpt-5.6-preview',
        reasoning: ReasoningSelection::effort(ReasoningEffort::Low),
    ))->withModelProfile($catalog->find('openai', 'gpt-5.6-preview'));

    expect(fn () => (new InferenceRequestPreflight())->apply($lossy))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new InferenceRequestPreflight())->apply($unsupported))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new InferenceRequestPreflight())->apply($nearMatch))->toThrow(InvalidArgumentException::class);
});

it('injects native Gemini controls after its formatter', function () {
    $config = new LLMConfig(model: 'gemini-3.1-pro-preview');
    $body = new ReasoningBodyFormat(
        new GeminiBodyFormat($config, new GeminiMessageFormat()),
        new ConfiguredReasoningTranslator(ReasoningWireFormat::Gemini),
    );
    $profile = ModelCatalog::bundled()->find('gemini', 'gemini-3.1-pro-preview');
    $request = (new InferenceRequest(
        messages: Messages::fromString('Think.'),
        reasoning: ReasoningSelection::effort(ReasoningEffort::High),
    ))->withModelProfile($profile);

    expect($body->toRequestBody($request))
        ->toHaveKey('generationConfig.thinkingConfig.thinkingLevel', 'HIGH');
});

it('rejects typed and raw reasoning configuration conflicts', function () {
    $config = new LLMConfig(model: 'gemini-3.1-pro-preview');
    $body = new ReasoningBodyFormat(
        new GeminiBodyFormat($config, new GeminiMessageFormat()),
        new ConfiguredReasoningTranslator(ReasoningWireFormat::Gemini),
    );
    $request = (new InferenceRequest(
        options: ['thinkingConfig' => ['thinkingLevel' => 'LOW']],
        reasoning: ReasoningSelection::effort(ReasoningEffort::High),
    ))->withModelProfile(ModelCatalog::bundled()->find('gemini', 'gemini-3.1-pro-preview'));

    expect(fn () => $body->toRequestBody($request))->toThrow(InvalidArgumentException::class);
});

it('allows provider default through a driver without typed reasoning wire support', function () {
    expect((new UnsupportedReasoningTranslator())->translate(
        ReasoningCapabilities::unknown(),
        ReasoningSelection::providerDefault(),
    )->options->toArray())->toBe([]);
});
