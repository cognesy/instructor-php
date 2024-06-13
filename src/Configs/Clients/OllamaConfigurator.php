<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\Ollama\OllamaConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Configurator;
use Cognesy\Instructor\Events\EventDispatcher;

class OllamaConfigurator extends Configurator
{
    public function setup(Configuration $config): void {
        $config->declare(
            class: OllamaClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(OllamaConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => 'llama2',
                'defaultMaxTokens' => 256,
            ],
            getInstance: function($context) {
                $object = new OllamaClient(
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
            class: OllamaConnector::class,
            context: [
                'apiKey' => $_ENV['OLLAMA_API_KEY'] ?? 'ollama',
                'baseUrl' => $_ENV['OLLAMA_BASE_URI'] ?? '',
                'connectTimeout' => 3,
                'requestTimeout' => 90,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'ollama:llama2',
            context: [
                'label' => 'Ollama LLaMA2',
                'type' => 'llama2',
                'name' => 'llama2:latest',
                'maxTokens' => 4096,
                'contextSize' => 4096,
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
            name: 'ollama:llama3',
            context: [
                'label' => 'Ollama LLaMA2',
                'type' => 'llama2',
                'name' => 'llama3:latest',
                'maxTokens' => 4096,
                'contextSize' => 4096,
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