<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use Cognesy\Polyglot\Inference\PendingInference;

it('sends structured prompt cached context through provider-native anthropic mapping', function () {
    $config = new StructuredOutputConfig(outputMode: OutputMode::Json);
    $responseModel = (new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        new EventDispatcher(),
    ))->fromAny(\stdClass::class);

    $execution = new StructuredOutputExecution(
        request: new StructuredOutputRequest(
            messages: Messages::fromString('Live conversation.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            prompt: 'LIVE TASK',
            model: 'claude-3-sonnet',
            cachedContext: new CachedContext(
                system: 'Cached system.',
                prompt: 'CACHED TASK',
                messages: Messages::fromString('Cached conversation.'),
            ),
        ),
        config: $config,
        responseModel: $responseModel,
    );

    $capturingInference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(?InferenceRequest $request = null): PendingInference
        {
            $this->captured = $request;
            assert($request instanceof InferenceRequest);

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new \Cognesy\Instructor\Tests\Support\FakeInferenceDriver(),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    (new InferenceProvider($capturingInference, new StructuredPromptRequestMaterializer()))
        ->getInference($execution);

    $body = (new AnthropicBodyFormat(
        new LLMConfig(
            apiUrl: 'https://api.anthropic.com',
            apiKey: 'KEY',
            endpoint: '/v1/messages',
            model: 'claude-3-sonnet',
            driver: 'anthropic',
        ),
        new OpenAIMessageFormat(),
    ))->toRequestBody($capturingInference->captured);

    expect($capturingInference->captured?->cachedContext()?->isEmpty())->toBeFalse()
        ->and($body['system'][0]['cache_control']['type'] ?? null)->toBe('ephemeral')
        ->and($body['system'][0]['text'] ?? '')->toContain('CACHED TASK')
        ->and($body['system'][1]['text'] ?? '')->toContain('LIVE TASK');
});

it('sends structured prompt cached context through provider-native openresponses mapping', function () {
    $config = new StructuredOutputConfig(outputMode: OutputMode::Json);
    $responseModel = (new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        new EventDispatcher(),
    ))->fromAny(\stdClass::class);

    $execution = new StructuredOutputExecution(
        request: new StructuredOutputRequest(
            messages: Messages::fromString('Live conversation.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            prompt: 'LIVE TASK',
            model: 'gpt-4o-mini',
            cachedContext: new CachedContext(
                system: 'Cached system.',
                prompt: 'CACHED TASK',
                messages: Messages::fromString('Cached conversation.'),
            ),
        ),
        config: $config,
        responseModel: $responseModel,
    );

    $capturingInference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(?InferenceRequest $request = null): PendingInference
        {
            $this->captured = $request;
            assert($request instanceof InferenceRequest);

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new \Cognesy\Instructor\Tests\Support\FakeInferenceDriver(),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    (new InferenceProvider($capturingInference, new StructuredPromptRequestMaterializer()))
        ->getInference($execution);

    $body = (new OpenResponsesBodyFormat(
        new LLMConfig(
            apiUrl: 'https://api.openai.com',
            apiKey: 'KEY',
            endpoint: '/v1/responses',
            model: 'gpt-4o-mini',
            driver: 'openresponses',
        ),
        new OpenResponsesMessageFormat(),
    ))->toRequestBody($capturingInference->captured);

    expect($capturingInference->captured?->cachedContext()?->isEmpty())->toBeFalse()
        ->and($body['instructions'] ?? '')->toContain('CACHED TASK')
        ->and($body['instructions'] ?? '')->toContain('LIVE TASK')
        ->and(array_map(static fn(array $item): string => $item['role'] ?? '', $body['input'] ?? []))->not->toContain('system');
});
