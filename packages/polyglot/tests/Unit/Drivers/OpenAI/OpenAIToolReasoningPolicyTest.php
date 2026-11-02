<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

function toolReasoningBody(
    string $model,
    bool $withTools = true,
    array $options = [],
    ?ReasoningSelection $reasoning = null,
    string $driver = 'openai',
): array {
    $config = new LLMConfig(model: 'gpt-6-luna', driver: $driver);
    $format = match ($driver) {
        'openai' => new OpenAIBodyFormat($config, new OpenAIMessageFormat()),
        default => new OpenAICompatibleBodyFormat($config, new OpenAIMessageFormat()),
    };
    $tools = ToolDefinitions::fromArray([[
        'type' => 'function',
        'function' => ['name' => 'extract', 'parameters' => ['type' => 'object', 'properties' => []]],
    ]]);

    return $format->toRequestBody(new InferenceRequest(
        messages: Messages::fromString('Jason is 25'),
        model: $model,
        tools: $withTools ? $tools : null,
        options: $options,
        reasoning: $reasoning,
    ));
}

it('disables reasoning for tool calls on reasoning-by-default OpenAI models', function (string $model) {
    expect(toolReasoningBody($model))->toHaveKey('reasoning_effort', 'none');
})->with(['gpt-6-luna', 'gpt-5.6', 'gpt-6-sol', 'gpt-6.1-terra', 'gpt-5.6-luna']);

it('omits reasoning_effort for models that reject it or cannot disable reasoning', function (string $model) {
    expect(toolReasoningBody($model))->not->toHaveKey('reasoning_effort');
})->with([
    'non-reasoning catalog model' => 'gpt-4o-mini',
    'uncataloged legacy model' => 'gpt-4.1-mini',
    'reasoning cannot be disabled' => 'gpt-6.1-sol',
]);

it('leaves requests without tools unchanged', function () {
    expect(toolReasoningBody('gpt-6-luna', withTools: false))->not->toHaveKey('reasoning_effort');
});

it('keeps explicit raw reasoning_effort options', function () {
    expect(toolReasoningBody('gpt-6-luna', options: ['reasoning_effort' => 'low']))
        ->toHaveKey('reasoning_effort', 'low');
});

it('leaves typed reasoning selections to the reasoning translator', function () {
    $body = toolReasoningBody('gpt-6-luna', reasoning: ReasoningSelection::effort(ReasoningEffort::High));

    expect($body)->not->toHaveKey('reasoning_effort');
});

it('applies only to the native OpenAI driver', function () {
    expect(toolReasoningBody('gpt-6-luna', driver: 'openai-compatible'))->not->toHaveKey('reasoning_effort');
});
