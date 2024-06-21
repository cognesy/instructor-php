<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class FireworksConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class:FireworksAIClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(FireworksAIConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['FIREWORKSAI_DEFAULT_MODEL'] ?? 'accounts/fireworks/models/mixtral-8x7b-instruct',
                'defaultMaxTokens' => $_ENV['FIREWORKSAI_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new FireworksAIClient(
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
            class:FireworksAIConnector::class,
            context: [
                'apiKey' => $_ENV['FIREWORKSAI_API_KEY'] ?? '',
                'baseUrl' => $_ENV['FIREWORKSAI_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['FIREWORKSAI_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['FIREWORKSAI_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'fireworks:mixtral-8x7b',
            context: [
                'label' => 'Fireworks Mixtral 8x7B',
                'type' => 'mixtral',
                'name' => 'accounts/fireworks/models/mixtral-8x7b-instruct',
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