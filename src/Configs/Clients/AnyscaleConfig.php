<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class AnyscaleConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->declare(
            class: AnyscaleClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(AnyscaleConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['ANYSCALE_DEFAULT_MODEL'] ?? 'mistralai/Mixtral-8x7B-Instruct-v0.1',
                'defaultMaxTokens' => $_ENV['ANYSCALE_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new AnyscaleClient(
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
            class: AnyscaleConnector::class,
            context: [
                'apiKey' => $_ENV['ANYSCALE_API_KEY'] ?? '',
                'baseUrl' => $_ENV['ANYSCALE_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['ANYSCALE_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['ANYSCALE_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'anyscale:mixtral-8x7b',
            context: [
                'label' => 'Anyscale Mixtral 8x7B',
                'type' => 'mixtral',
                'name' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
                'maxTokens' => 4096,
                'contextSize' => 200_000,
                'inputCost' => 1,
                'outputCost' => 1,
            ],
        );

    }
}