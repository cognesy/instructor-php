<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class TogetherConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: TogetherAIClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(TogetherAIConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['TOGETHERAI_DEFAULT_MODEL'] ?? 'mistralai/Mixtral-8x7B-Instruct-v0.1',
                'defaultMaxTokens' => $_ENV['TOGETHERAI_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new TogetherAIClient(
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
            class:TogetherAIConnector::class,
            context: [
                'apiKey' => $_ENV['TOGETHERAI_API_KEY'] ?? '',
                'baseUrl' => $_ENV['TOGETHERAI_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['TOGETHERAI_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['TOGETHERAI_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->object(
            class: ModelParams::class,
            name: 'together:mixtral-8x7b',
            context: [
                'label' => 'Together Mixtral 8x7B',
                'type' => 'mixtral',
                'name' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
                'maxTokens' => 4096,
                'contextSize' => 4096,
                'inputCost' => 1,
                'outputCost' => 1,

            ],
        );

    }
}