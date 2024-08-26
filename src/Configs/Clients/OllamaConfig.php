<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
//use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\Ollama\OllamaConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class OllamaConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: OllamaClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(OllamaConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['OLLAMA_DEFAULT_MODEL'] ?? 'llama2',
                'defaultMaxTokens' => $_ENV['OLLAMA_DEFAULT_MAX_TOKENS'] ?? 1024,
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

        $config->object(
            class: OllamaConnector::class,
            context: [
                'apiKey' => $_ENV['OLLAMA_API_KEY'] ?? 'ollama',
                'baseUrl' => $_ENV['OLLAMA_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['OLLAMA_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['OLLAMA_REQUEST_TIMEOUT'] ?? 90,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

//        $config->object(
//            class: ModelParams::class,
//            name: 'ollama:llama2',
//            context: [
//                'label' => 'Ollama LLaMA2',
//                'type' => 'llama2',
//                'name' => 'llama2:latest',
//                'maxTokens' => 4096,
//                'contextSize' => 4096,
//                'inputCost' => 1,
//                'outputCost' => 1,
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'ollama:llama3',
//            context: [
//                'label' => 'Ollama LLaMA2',
//                'type' => 'llama2',
//                'name' => 'llama3:latest',
//                'maxTokens' => 4096,
//                'contextSize' => 4096,
//                'inputCost' => 1,
//                'outputCost' => 1,
//            ],
//        );

    }
}