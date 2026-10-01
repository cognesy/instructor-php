<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

function echoTestConfig(array $options = []): LLMConfig {
    return LLMConfig::fromArray([
        'driver' => 'echo',
        'apiUrl' => 'https://echo.fulcrum.inc/api/v1',
        'endpoint' => '/chat/completions',
        'apiKey' => 'test',
        'model' => 'echo',
        'maxTokens' => 20480,
        'options' => $options,
    ]);
}

function echoTestMock(): MockHttpDriver {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://echo.fulcrum.inc/api/v1/chat/completions')
        ->replyJson([
            'choices' => [[
                'message' => [
                    'content' => 'Hope is the thing with feathers.',
                    'reasoning_content' => 'Short lines, dashes, slant rhyme.',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7],
        ]);
    return $mock;
}

function echoTestInference(LLMConfig $config, MockHttpDriver $mock): Inference {
    $http = (new HttpClientBuilder())->withDriver($mock)->create();
    $runtime = InferenceRuntime::fromConfig($config, httpClient: $http, models: ModelCatalog::discover());
    return Inference::fromRuntime($runtime)
        ->withMessages(Messages::fromString('Explain GRPO in one paragraph.'));
}

function echoSentBody(MockHttpDriver $mock): array {
    return json_decode($mock->getLastRequest()?->body()->toString() ?? '{}', true, flags: JSON_THROW_ON_ERROR);
}

it('sends the preset persona as a top-level body field', function () {
    $mock = echoTestMock();

    $response = echoTestInference(echoTestConfig(['persona' => 'Emily Dickinson']), $mock)->response();

    $body = echoSentBody($mock);
    expect($body['persona'])->toBe('Emily Dickinson')
        ->and($body['model'])->toBe('echo')
        ->and($body['max_tokens'])->toBe(20480)
        ->and($body)->not->toHaveKey('max_completion_tokens')
        ->and($response->message()->content()->toString())->toBe('Hope is the thing with feathers.')
        ->and($response->message()->reasoningContent())->toBe('Short lines, dashes, slant rhyme.');
});

it('lets a request persona override the preset persona', function () {
    $mock = echoTestMock();

    echoTestInference(echoTestConfig(['persona' => 'Emily Dickinson']), $mock)
        ->withOptions(['persona' => 'Joan Didion'])
        ->response();

    expect(echoSentBody($mock)['persona'])->toBe('Joan Didion');
});

it('renders reasoning effort from the bundled model spec as reasoning_effort', function () {
    $mock = echoTestMock();

    echoTestInference(echoTestConfig(['persona' => 'Joan Didion']), $mock)
        ->withReasoning(ReasoningSelection::effort(ReasoningEffort::High))
        ->response();

    expect(echoSentBody($mock)['reasoning_effort'])->toBe('high');
});

it('fails before sending when persona is missing', function (array $options) {
    $mock = echoTestMock();

    expect(fn() => echoTestInference(echoTestConfig($options), $mock)->response())
        ->toThrow(InvalidArgumentException::class, "Echo requires a non-empty string 'persona' option")
        ->and($mock->getLastRequest())->toBeNull();
})->with([
    'absent' => [[]],
    'blank' => [['persona' => '   ']],
    'non-string' => [['persona' => ['name' => 'Joan Didion']]],
]);
