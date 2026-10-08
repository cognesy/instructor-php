<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Creation;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Exceptions\UnsupportedBatchOperation;
use Cognesy\Polyglot\BatchInference\Drivers\OpenAI\OpenAIBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\Anthropic\AnthropicBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\Mistral\MistralBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\Groq\GroqBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\Together\TogetherBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\Qwen\QwenBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\Gemini\GeminiBatchDriver;
use Cognesy\Polyglot\BatchInference\Drivers\XAI\XAIBatchDriver;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;

final readonly class BatchDriverRegistry
{
    /** @param array<string, callable(BatchConfig, CanSendHttpRequests, CanUploadBatchFile, CanSendBatchFileBody): CanDriveBatchInference> $factories */
    private function __construct(private array $factories = [])
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function default(): self
    {
        $openAI = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new OpenAIBatchDriver($config, new BatchHttpTransport($http), $uploader);
        $anthropic = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new AnthropicBatchDriver($config, new BatchHttpTransport($http), $bodySender);
        $mistral = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new MistralBatchDriver($config, new BatchHttpTransport($http), $uploader, $bodySender);
        $groq = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new GroqBatchDriver($config, new BatchHttpTransport($http), $uploader);
        $together = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new TogetherBatchDriver($config, new BatchHttpTransport($http), $uploader);
        $qwen = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new QwenBatchDriver($config, new BatchHttpTransport($http), $uploader);
        $gemini = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new GeminiBatchDriver($config, new BatchHttpTransport($http), $bodySender);
        $xai = static fn (BatchConfig $config, CanSendHttpRequests $http, CanUploadBatchFile $uploader, CanSendBatchFileBody $bodySender): CanDriveBatchInference
            => new XAIBatchDriver($config, new BatchHttpTransport($http), $uploader);
        return new self([
            'openai-chat' => $openAI,
            'openai-responses' => $openAI,
            'anthropic-messages' => $anthropic,
            'mistral-chat' => $mistral,
            'groq-chat' => $groq,
            'together-chat' => $together,
            'qwen-chat' => $qwen,
            'gemini-generate-content' => $gemini,
            'xai-chat' => $xai,
        ]);
    }

    /** @param callable(BatchConfig, CanSendHttpRequests, CanUploadBatchFile, CanSendBatchFileBody): CanDriveBatchInference $factory */
    public function withDriver(string $name, callable $factory): self
    {
        $factories = $this->factories;
        $factories[$name] = $factory;
        return new self($factories);
    }

    public function makeDriver(
        BatchConfig $config,
        CanSendHttpRequests $http,
        CanUploadBatchFile $uploader,
        CanSendBatchFileBody $bodySender,
    ): CanDriveBatchInference {
        $factory = $this->factories[$config->codec()] ?? null;
        if ($factory === null) {
            throw new UnsupportedBatchOperation("No native batch driver for codec {$config->codec()}.");
        }

        return $factory($config, $http, $uploader, $bodySender);
    }
}
