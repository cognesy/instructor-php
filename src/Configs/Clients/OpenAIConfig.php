<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class OpenAIConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: OpenAIClient::class,
            name: CanCallApi::class, // default client
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(OpenAIConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['OPENAI_DEFAULT_MODEL'] ?? 'gpt-4o-mini',
                'defaultMaxTokens' => $_ENV['OPENAI_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new OpenAIClient(
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
            class: OpenAIConnector::class,
            context: [
                'apiKey' => $_ENV['OPENAI_API_KEY'] ?? '',
                'baseUrl' => $_ENV['OPENAI_BASE_URI'] ?? '',
                'organization' => $_ENV['OPENAI_ORGANIZATION'] ?? '',
                'connectTimeout' => $_ENV['OPENAI_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['OPENAI_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openai:gpt-4o',
            context: [
                'label' => 'OpenAI GPT4o',
                'type' => 'gpt4',
                'name' => 'gpt-4o',
                'maxTokens' => 128_000,
                'contextSize' => 128_000,
                'inputCost' => 5,
                'outputCost' => 15,

            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openai:gpt-4o-mini',
            context: [
                'label' => 'OpenAI GPT4o Mini',
                'type' => 'gpt4',
                'name' => 'gpt-4o-mini',
                'maxTokens' => 128_000,
                'contextSize' => 128_000,
                'inputCost' => 0.15,
                'outputCost' => 0.60,

            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openai:gpt-4-turbo',
            context: [
                'label' => 'OpenAI GPT4 Turbo',
                'type' => 'gpt4',
                'name' => 'gpt-4-turbo',
                'maxTokens' => 128_000,
                'contextSize' => 128_000,
                'inputCost' => 10,
                'outputCost' => 30,

            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openai:gpt-4',
            context: [
                'label' => 'OpenAI GPT 4',
                'type' => 'gpt4',
                'name' => 'gpt-4',
                'maxTokens' => 8192,
                'contextSize' => 8192,
                'inputCost' => 1,
                'outputCost' => 1,

            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openai:gpt-4-32k',
            context: [
                'label' => 'OpenAI GPT 4 32k',
                'type' => 'gpt4',
                'name' => 'gpt-4-32k',
                'maxTokens' => 32_768,
                'contextSize' => 32_768,
                'inputCost' => 1,
                'outputCost' => 1,

            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'openai:gpt-3.5-turbo',
            context: [
                'label' => 'OpenAI GPT 3.5 Turbo',
                'type' => 'gpt35',
                'name' => 'gpt-3.5-turbo',
                'maxTokens' => 4096,
                'contextSize' => 16_385,
                'inputCost' => 0.5,
                'outputCost' => 1.5,

            ],
        );

    }
}