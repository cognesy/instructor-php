<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Anthropic\AnthropicConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class AnthropicConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->declare(
            class: AnthropicClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(AnthropicConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => 'claude-3-haiku-20240307',
                'defaultMaxTokens' => 256,
            ],
            getInstance: function($context) {
                $object = new AnthropicClient(
                    events: $context['events'],
                    connector: $context['connector'],
                );
                $object->withApiRequestFactory($context['apiRequestFactory']);
                $object->defaultModel = $context['defaultModel'];
                $object->defaultMaxTokens = $context['defaultMaxTokens'];
                return $object;
            },
        );

        $config->declare(
            class: AnthropicConnector::class,
            context: [
                'apiKey' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
                'baseUrl' => $_ENV['ANTHROPIC_BASE_URI'] ?? '',
                'connectTimeout' => 3,
                'requestTimeout' => 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'anthropic:claude-3-haiku',
            context: [
                'label' => 'Claude 3 Haiku',
                'type' => 'claude3',
                'name' => 'claude-3-haiku-20240307',
                'maxTokens' => 4096,
                'contextSize' => 200_000,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'assistant',
                    'system' => 'system'
                ],
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'anthropic:claude-3-sonnet',
            context: [
                'label' => 'Claude 3 Sonnet',
                'type' => 'claude3',
                'name' => 'claude-3-sonnet-20240229',
                'maxTokens' => 4096,
                'contextSize' => 200_000,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'assistant',
                    'system' => 'system'
                ],
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'anthropic:claude-3-opus',
            context: [
                'label' => 'Claude 3 Opus',
                'type' => 'claude3',
                'name' => 'claude-3-opus-20240229',
                'maxTokens' => 4096,
                'contextSize' => 200_000,
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