<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Azure\AzureConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Configurator;
use Cognesy\Instructor\Events\EventDispatcher;

class AzureConfigurator extends Configurator
{
    public function setup(Configuration $config): void {
        $config->declare(
            class: AzureClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(AzureConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => 'gpt-4-turbo-preview',
                'defaultMaxTokens' => 256,
            ],
            getInstance: function($context) {
                $object = new AzureClient(
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
            class: AzureConnector::class,
            context: [
                'apiKey' => $_ENV['AZURE_API_KEY'] ?? '',
                'resourceName' => $_ENV['AZURE_RESOURCE_NAME'] ?? '',
                'deploymentId' => $_ENV['AZURE_DEPLOYMENT_ID'] ?? '',
                'apiVersion' => $_ENV['AZURE_API_VERSION'] ?? '',
                'baseUrl' => $_ENV['OPENAI_BASE_URI'] ?? '',
                'connectTimeout' => 3,
                'requestTimeout' => 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'azure:gpt-3.5-turbo',
            context: [
                'label' => 'Azure GPT 3.5 Turbo',
                'type' => 'gpt35',
                'name' => 'gpt-3.5-turbo',
                'maxTokens' => 4_096,
                'contextSize' => 16_385,
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