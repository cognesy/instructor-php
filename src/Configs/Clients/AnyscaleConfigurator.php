<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Configurator;
use Cognesy\Instructor\Events\EventDispatcher;

class AnyscaleConfigurator extends Configurator
{
    public function setup(Configuration $config): void {
        $config->declare(
            class: AnyscaleClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(AnyscaleConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
                'defaultMaxTokens' => 256,
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
                'connectTimeout' => 3,
                'requestTimeout' => 30,
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