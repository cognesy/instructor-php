<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Groq\GroqConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Configurator;
use Cognesy\Instructor\Events\EventDispatcher;

class GroqConfigurator extends Configurator
{
    public function setup(Configuration $config): void {
        $config->declare(
            class: GroqClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(GroqConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => 'llama3-8b-8192',
                'defaultMaxTokens' => 256,
            ],
            getInstance: function($context) {
                $object = new GroqClient(
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
            class: GroqConnector::class,
            context: [
                'apiKey' => $_ENV['GROQ_API_KEY'] ?? '',
                'baseUrl' => $_ENV['GROQ_BASE_URI'] ?? '',
                'connectTimeout' => 3,
                'requestTimeout' => 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'groq:llama3-8b',
            context: [
                'label' => 'GroQ LLaMA3 8B',
                'type' => 'llama3',
                'name' => 'llama3-8b-8192',
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

        $config->declare(
            class: ModelParams::class,
            name: 'groq:llama3-70b',
            context: [
                'label' => 'GroQ LLaMA3 70B',
                'type' => 'llama3',
                'name' => 'llama3-70b-8192',
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

        $config->declare(
            class: ModelParams::class,
            name: 'groq:mixtral-8x7b',
            context: [
                'label' => 'GroQ Mixtral 8x7B',
                'type' => 'mixtral',
                'name' => 'mixtral-8x7b-32768',
                'maxTokens' => 32768,
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

        $config->declare(
            class: ModelParams::class,
            name: 'groq:gemma-7b',
            context: [
                'label' => 'GroQ Gemma 7B',
                'type' => 'gemma',
                'name' => 'gemma-7b-it',
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
    }
}