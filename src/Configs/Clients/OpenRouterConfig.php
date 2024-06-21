<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class OpenRouterConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: OpenRouterClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(OpenRouterConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['OPENROUTER_DEFAULT_MODEL'] ?? 'gpt-3.5-turbo',
                'defaultMaxTokens' => $_ENV['OPENROUTER_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new OpenRouterClient(
                    events: $context['events'],
                    connector: $context['connector'],
                );
                $object->withApiRequestFactory($context['apiRequestFactory']);
                $object->defaultModel = $context['defaultModel'];
                $object->defaultMaxTokens = $context['defaultMaxTokens'];
                return $object;
            },
        );

        $config->object(
            class: OpenRouterConnector::class,
            context: [
                'apiKey' => $_ENV['OPENROUTER_API_KEY'] ?? '',
                'baseUrl' => $_ENV['OPENROUTER_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['OPENROUTER_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['OPENROUTER_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openrouter:llama3',
            context: [
                'label' => 'OpenRouter LLaMA3 8B',
                'type' => 'llama3',
                'name' => 'meta-llama/llama-3-8b-instruct:extended',
                'maxTokens' => 8192,
                'contextSize' => 8192,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'assistant',
                    'system' => 'system'
                ],
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openrouter:mixtral-8x7b',
            context: [
                'label' => 'OpenRouter Mixtral 8x7b',
                'type' => 'mixtral',
                'name' => 'mistralai/mixtral-8x7b',
                'maxTokens' => 32_768,
                'contextSize' => 32768,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'assistant',
                    'system' => 'system'
                ],
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openrouter:mistral-7b',
            context: [
                'label' => 'OpenRouter Mistral 7B Instruct',
                'type' => 'mistral',
                'name' => 'mistralai/mistral-7b-instruct:free',
                'maxTokens' => 32_768,
                'contextSize' => 32768,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'assistant',
                    'system' => 'system'
                ],
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openrouter:gpt-3.5-turbo',
            context: [
                'label' => 'OpenRouter GPT 3.5 Turbo',
                'type' => 'gpt3.5',
                'name' => 'gpt-3.5-turbo',
                'maxTokens' => 32_768,
                'contextSize' => 32_768,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'assistant',
                    'system' => 'system'
                ],
            ],
        );

    }
}