<?php declare(strict_types=1);

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;

it('attaches exact model profiles so typed reasoning reaches the provider request', function () {
    $mock = new MockHttpDriver();
    $mock->addResponse(MockHttpResponseFactory::success(body: (string) json_encode([
        'id' => 'resp_1',
        'status' => 'completed',
        'output' => [[
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'output_text', 'text' => 'done']],
        ]],
    ])));
    $httpClient = (new HttpClientBuilder())->withDriver($mock)->create();
    $llm = LLMProvider::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.example.test/v1',
        apiKey: 'test-key',
        endpoint: '/responses',
        model: 'gpt-6-luna',
        driver: 'openai-responses',
    ));
    $driver = (new ToolCallingDriver(
        inference: InferenceRuntime::fromProvider($llm, httpClient: $httpClient),
        llm: $llm,
        httpClient: $httpClient,
    ))
        ->withModelCatalog(ModelCatalog::discover())
        ->withReasoning(ReasoningSelection::effort(ReasoningEffort::High));

    $driver->useTools(AgentState::empty()->withMessages(Messages::fromString('Hi')));
    $body = json_decode($mock->getLastRequest()?->body()->toString() ?? '{}', true);

    expect($body['reasoning'] ?? null)->toBe(['effort' => 'high']);
});
