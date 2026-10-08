<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchCancellationException;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

final class BatchCancellationScriptedHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    public function __construct(private ?int $status, private string $body = '{}')
    {
    }

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        if ($this->status === null) {
            throw new NetworkException('Cancellation response was lost.', $request);
        }
        return HttpResponse::sync($this->status, [], $this->body);
    }
}

it('preserves cancellation certainty and the saved reference across HTTP batch providers', function () {
    foreach ([
        ['openai', 'https://api.openai.com/v1', 'gpt-test', '/v1/chat/completions', 'openai-chat'],
        ['openai-responses', 'https://api.openai.com/v1', 'gpt-test', '/v1/responses', 'openai-responses'],
        ['anthropic', 'https://api.anthropic.com/v1', 'claude-test', '/v1/messages', 'anthropic-messages'],
        ['mistral', 'https://api.mistral.ai/v1', 'mistral-test', '/v1/chat/completions', 'mistral-chat-file'],
        ['gemini', 'https://generativelanguage.googleapis.com/v1beta', 'gemini-2.5-flash-lite', 'models/gemini-2.5-flash-lite:batchGenerateContent', 'gemini-generate-content'],
        ['qwen', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'qwen-turbo', '/v1/chat/completions', 'qwen-chat'],
        ['xai', 'https://api.x.ai/v1', 'grok-4.3', '/v1/chat/completions', 'xai-chat'],
        ['groq', 'https://api.groq.com/openai/v1', 'llama-3.1-8b-instant', '/v1/chat/completions', 'groq-chat'],
        ['together', 'https://api.together.ai/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo', '/v1/chat/completions', 'together-chat'],
    ] as [$driver, $apiUrl, $model, $route, $codec]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl,
            apiKey: 'test-secret',
            endpoint: '/chat/completions',
            model: $model,
            driver: $driver,
        ));
        $id = $config->provider() === 'gemini' ? 'batches/batch-test' : 'batch-test';
        $reference = new BatchReference(new BatchJobId($id), $config->provider(), $config->scope(), $route, $codec);

        foreach ([
            [400, BatchMutationCertainty::Rejected],
            [408, BatchMutationCertainty::MayHaveSucceeded],
            [null, BatchMutationCertainty::MayHaveSucceeded],
        ] as [$status, $certainty]) {
            $http = new BatchCancellationScriptedHttp($status);
            $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));

            try {
                $batches->cancel($reference);
                test()->fail("Expected {$driver} cancellation failure.");
            } catch (BatchCancellationException $error) {
                expect($error->reference())->toBe($reference)
                    ->and($error->certainty())->toBe($certainty)
                    ->and(count($http->requests))->toBe(1)
                    ->and($http->requests[0]->method())->toBe('POST');
            }
        }
    }
});

it('recognizes completed jobs returned by provider-specific cancellation responses', function () {
    foreach ([
        ['anthropic', 'https://api.anthropic.com/v1', 'claude-test', '/v1/messages', 'anthropic-messages', ['id' => 'batch-test', 'processing_status' => 'ended'], 'ended'],
        ['mistral', 'https://api.mistral.ai/v1', 'mistral-test', '/v1/chat/completions', 'mistral-chat-file', ['id' => 'batch-test', 'status' => 'SUCCESS'], 'SUCCESS'],
        ['qwen', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'qwen-turbo', '/v1/chat/completions', 'qwen-chat', ['id' => 'batch-test', 'status' => 'completed'], 'completed'],
        ['xai', 'https://api.x.ai/v1', 'grok-4.3', '/v1/chat/completions', 'xai-chat', ['batch_id' => 'batch-test', 'state' => ['num_requests' => 1, 'num_pending' => 0, 'num_success' => 1, 'num_error' => 0, 'num_cancelled' => 0]], 'completed'],
        ['groq', 'https://api.groq.com/openai/v1', 'llama-3.1-8b-instant', '/v1/chat/completions', 'groq-chat', ['id' => 'batch-test', 'status' => 'completed'], 'completed'],
        ['together', 'https://api.together.ai/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo', '/v1/chat/completions', 'together-chat', ['id' => 'batch-test', 'status' => 'COMPLETED'], 'COMPLETED'],
    ] as [$driver, $apiUrl, $model, $route, $codec, $response, $nativeStatus]) {
        $config = BatchConfig::fromLLMConfig(new LLMConfig(
            apiUrl: $apiUrl,
            apiKey: 'test-secret',
            endpoint: '/chat/completions',
            model: $model,
            driver: $driver,
        ));
        $reference = new BatchReference(new BatchJobId('batch-test'), $config->provider(), $config->scope(), $route, $codec, 1, true);
        $http = new BatchCancellationScriptedHttp(200, json_encode($response, JSON_THROW_ON_ERROR));
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));

        $receipt = $batches->cancel($reference);

        expect($receipt->reference())->toBe($reference)
            ->and($receipt->acknowledged())->toBeTrue()
            ->and($receipt->alreadyTerminal())->toBeTrue()
            ->and($receipt->providerStatus())->toBe($nativeStatus)
            ->and(count($http->requests))->toBe(1)
            ->and($http->requests[0]->method())->toBe('POST');
    }
});
