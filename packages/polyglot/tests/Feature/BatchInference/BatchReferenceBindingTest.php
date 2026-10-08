<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

final class NoNetworkBatchHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    public int $requests = 0;

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests++;
        throw new LogicException('Invalid batch reference reached HTTP.');
    }
}

final class OneResponseBatchHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    public int $requests = 0;

    public function __construct(private string $body)
    {
    }

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests++;
        return HttpResponse::sync(200, [], $this->body);
    }
}

it('rejects incompatible provider endpoint identities before any authenticated read', function () {
    foreach ([
        ['openai', 'https://api.openai.com/v1', 'gpt-test', '/v1/embeddings', 'openai-chat'],
        ['openai-responses', 'https://api.openai.com/v1', 'gpt-test', '/v1/chat/completions', 'openai-responses'],
        ['anthropic', 'https://api.anthropic.com/v1', 'claude-test', '/v1/chat/completions', 'anthropic-messages'],
        ['mistral', 'https://api.mistral.ai/v1', 'mistral-test', '/v1/moderations', 'mistral-chat-file'],
        ['gemini', 'https://generativelanguage.googleapis.com/v1beta', 'gemini-2.5-flash-lite', 'models/gemini-2.5-flash-lite:embedContent', 'gemini-generate-content'],
        ['qwen', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'qwen-turbo', '/v1/embeddings', 'qwen-chat'],
        ['xai', 'https://api.x.ai/v1', 'grok-4.3', '/v1/embeddings', 'xai-chat'],
        ['groq', 'https://api.groq.com/openai/v1', 'llama-3.1-8b-instant', '/v1/embeddings', 'groq-chat'],
        ['together', 'https://api.together.ai/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo', '/v1/embeddings', 'together-chat'],
    ] as [$driver, $apiUrl, $model, $route, $codec]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl,
            apiKey: 'test-secret',
            endpoint: '/chat/completions',
            model: $model,
            driver: $driver,
        ));
        $http = new NoNetworkBatchHttp();
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $id = $config->provider() === 'gemini' ? 'batches/batch-test' : 'batch-test';
        $reference = new BatchReference(new BatchJobId($id), $config->provider(), $config->scope(), $route, $codec);

        expect(fn () => $batches->retrieve($reference))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $batches->results($reference))->toThrow(InvalidArgumentException::class)
            ->and($http->requests)->toBe(0);
    }
});

it('rejects a supported endpoint paired with the wrong result codec before HTTP', function () {
    foreach ([
        ['openai', 'https://api.openai.com/v1', 'gpt-test', '/v1/chat/completions', 'openai-responses'],
        ['anthropic', 'https://api.anthropic.com/v1', 'claude-test', '/v1/messages', 'openai-chat'],
        ['mistral', 'https://api.mistral.ai/v1', 'mistral-test', '/v1/chat/completions', 'unsupported'],
        ['gemini', 'https://generativelanguage.googleapis.com/v1beta', 'gemini-2.5-flash-lite', 'models/gemini-2.5-flash-lite:batchGenerateContent', 'openai-chat'],
        ['qwen', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'qwen-turbo', '/v1/chat/completions', 'unsupported'],
        ['xai', 'https://api.x.ai/v1', 'grok-4.3', '/v1/chat/completions', 'unsupported'],
        ['groq', 'https://api.groq.com/openai/v1', 'llama-3.1-8b-instant', '/v1/chat/completions', 'unsupported'],
        ['together', 'https://api.together.ai/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo', '/v1/chat/completions', 'unsupported'],
    ] as [$driver, $apiUrl, $model, $route, $codec]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl,
            apiKey: 'test-secret',
            endpoint: '/chat/completions',
            model: $model,
            driver: $driver,
        ));
        $http = new NoNetworkBatchHttp();
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $id = $config->provider() === 'gemini' ? 'batches/batch-test' : 'batch-test';
        $reference = new BatchReference(new BatchJobId($id), $config->provider(), $config->scope(), $route, $codec);

        expect(fn () => $batches->retrieve($reference))->toThrow(InvalidArgumentException::class)
            ->and($http->requests)->toBe(0);
    }
});

it('keeps imported non-inference jobs inspectable while refusing unsupported result decoding', function () {
    foreach ([
        ['openai', 'https://api.openai.com/v1', 'gpt-test', 'validating'],
        ['mistral', 'https://api.mistral.ai/v1', 'mistral-test', 'QUEUED'],
        ['qwen', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'qwen-turbo', 'validating'],
        ['groq', 'https://api.groq.com/openai/v1', 'llama-3.1-8b-instant', 'validating'],
        ['together', 'https://api.together.ai/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo', 'VALIDATING'],
    ] as [$driver, $apiUrl, $model, $nativeStatus]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl,
            apiKey: 'test-secret',
            endpoint: '/chat/completions',
            model: $model,
            driver: $driver,
        ));
        $http = new OneResponseBatchHttp(json_encode([
            'id' => 'batch-imported',
            'endpoint' => '/v1/embeddings',
            'status' => $nativeStatus,
        ], JSON_THROW_ON_ERROR));
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $reference = new BatchReference(new BatchJobId('batch-imported'), $config->provider(), $config->scope(), '/v1/embeddings', 'unsupported');

        $job = $batches->retrieve($reference);

        expect($job->status())->toBe(BatchStatus::Pending)
            ->and($job->reference()->toArray())->toBe($reference->toArray())
            ->and(fn () => $batches->results($reference))->toThrow(BatchException::class)
            ->and($http->requests)->toBe(1);
    }
});

it('rejects a provider job whose observed route differs from its saved reference', function () {
    foreach ([
        ['openai', 'https://api.openai.com/v1', 'gpt-test', '/v1/chat/completions', 'openai-chat', [
            'id' => 'batch-test', 'endpoint' => '/v1/responses', 'status' => 'completed',
        ]],
        ['mistral', 'https://api.mistral.ai/v1', 'mistral-test', '/v1/chat/completions', 'mistral-chat-file', [
            'id' => 'batch-test', 'endpoint' => '/v1/moderations', 'status' => 'SUCCESS',
        ]],
        ['gemini', 'https://generativelanguage.googleapis.com/v1beta', 'gemini-2.5-flash-lite', 'models/gemini-2.5-flash-lite:batchGenerateContent', 'gemini-generate-content', [
            'name' => 'batches/batch-test', 'metadata' => ['model' => 'models/gemini-2.5-pro', 'state' => 'BATCH_STATE_SUCCEEDED'], 'done' => true,
        ]],
    ] as [$driver, $apiUrl, $model, $route, $codec, $response]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl,
            apiKey: 'test-secret',
            endpoint: '/chat/completions',
            model: $model,
            driver: $driver,
        ));
        $http = new OneResponseBatchHttp(json_encode($response, JSON_THROW_ON_ERROR));
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $id = $driver === 'gemini' ? 'batches/batch-test' : 'batch-test';
        $reference = new BatchReference(new BatchJobId($id), $config->provider(), $config->scope(), $route, $codec);

        expect(fn () => $batches->retrieve($reference))->toThrow(BatchException::class)
            ->and($http->requests)->toBe(1);
    }
});
