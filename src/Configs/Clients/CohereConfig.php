<?php
namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Cohere\CohereClient;
use Cognesy\Instructor\Clients\Cohere\CohereConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class CohereConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->declare(
            class: CohereClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(CohereConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['COHERE_DEFAULT_MODEL'] ?? 'cohere-r-plus',
                'defaultMaxTokens' => $_ENV['COHERE_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new CohereClient(
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
            class:CohereConnector::class,
            context: [
                'apiKey' => $_ENV['COHERE_API_KEY'] ?? '',
                'baseUrl' => $_ENV['COHERE_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['COHERE_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['COHERE_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'cohere:command-r-plus',
            context: [
                'label' => 'Cohere Command R Plus',
                'type' => 'cohere',
                'name' => 'command-r-plus',
                'maxTokens' => 4096,
                'contextSize' => 128_000,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'USER',
                    'assistant' => 'CHATBOT',
                    'system' => 'USER'
                ],
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'cohere:command-r',
            context: [
                'label' => 'Cohere Command R',
                'type' => 'cohere',
                'name' => 'command-r',
                'maxTokens' => 4096,
                'contextSize' => 128_000,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'USER',
                    'assistant' => 'CHATBOT',
                    'system' => 'USER'
                ],
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'cohere:command',
            context: [
                'label' => 'Cohere Command',
                'type' => 'cohere',
                'name' => 'command',
                'maxTokens' => 4096,
                'contextSize' => 4096,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'USER',
                    'assistant' => 'CHATBOT',
                    'system' => 'USER'
                ],
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'cohere:command-light',
            context: [
                'label' => 'Cohere Command Light',
                'type' => 'cohere',
                'name' => 'command-light',
                'maxTokens' => 4096,
                'contextSize' => 4096,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'USER',
                    'assistant' => 'CHATBOT',
                    'system' => 'USER'
                ],
            ],
        );

    }
}