<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
//use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Anthropic\AnthropicConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class AnthropicConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: AnthropicClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(AnthropicConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['ANTHROPIC_DEFAULT_MODEL'] ?? 'claude-3-5-sonnet-20240620',
                'defaultMaxTokens' => $_ENV['ANTHROPIC_DEFAULT_MAX_TOKENS'] ?? 1024,
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

        $config->object(
            class: AnthropicConnector::class,
            context: [
                'apiKey' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
                'baseUrl' => $_ENV['ANTHROPIC_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['ANTHROPIC_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['ANTHROPIC_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

//        $config->object(
//            class: ModelParams::class,
//            name: 'anthropic:claude-3-haiku',
//            context: [
//                'label' => 'Claude 3 Haiku',
//                'type' => 'claude3',
//                'name' => 'claude-3-haiku-20240307',
//                'maxTokens' => 4096,
//                'contextSize' => 200_000,
//                'inputCost' => 0.25,
//                'outputCost' => 1.25,
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'anthropic:claude-3.5-sonnet',
//            context: [
//                'label' => 'Claude 3.5 Sonnet',
//                'type' => 'claude3',
//                'name' => 'claude-3-5-sonnet-20240620',
//                'maxTokens' => 4096,
//                'contextSize' => 200_000,
//                'inputCost' => 3,
//                'outputCost' => 15,
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'anthropic:claude-3-sonnet',
//            context: [
//                'label' => 'Claude 3 Sonnet',
//                'type' => 'claude3',
//                'name' => 'claude-3-sonnet-20240229',
//                'maxTokens' => 4096,
//                'contextSize' => 200_000,
//                'inputCost' => 3,
//                'outputCost' => 15,
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'anthropic:claude-3-opus',
//            context: [
//                'label' => 'Claude 3 Opus',
//                'type' => 'claude3',
//                'name' => 'claude-3-opus-20240229',
//                'maxTokens' => 4096,
//                'contextSize' => 200_000,
//                'inputCost' => 15,
//                'outputCost' => 75,
//            ],
//        );

    }
}