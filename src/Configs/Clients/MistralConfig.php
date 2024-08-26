<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
//use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Mistral\MistralConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class MistralConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->object(
            class: MistralClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(MistralConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['MISTRAL_DEFAULT_MODEL'] ?? 'mistral-small-latest',
                'defaultMaxTokens' => $_ENV['MISTRAL_DEFAULT_MAX_TOKENS'] ?? 1024,
            ],
            getInstance: function($context) {
                $object = new MistralClient(
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
            class: MistralConnector::class,
            context: [
                'apiKey' => $_ENV['MISTRAL_API_KEY'] ?? '',
                'baseUrl' => $_ENV['MISTRAL_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['MISTRAL_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['MISTRAL_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

//        $config->object(
//            class: ModelParams::class,
//            name: 'mistral:mistral-7b',
//            context: [
//                'label' => 'Mistral Mistral 7B',
//                'type' => 'mistral',
//                'name' => 'open-mistral-7b',
//                'maxTokens' => 32_000,
//                'contextSize' => 32_000,
//                'inputCost' => 1,
//                'outputCost' => 1,
//
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'mistral:mixtral-8x7b',
//            context: [
//                'label' => 'Mistral Mixtral 8x7B',
//                'type' => 'mixtral',
//                'name' => 'open-mixtral-8x7b',
//                'maxTokens' => 32_000,
//                'contextSize' => 32_000,
//                'inputCost' => 1,
//                'outputCost' => 1,
//
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'mistral:mixtral-8x22b',
//            context: [
//                'label' => 'Mistral Mixtral 8x22B',
//                'type' => 'mixtral',
//                'name' => 'open-mixtral-8x22b',
//                'maxTokens' => 64_000,
//                'contextSize' => 64_000,
//                'inputCost' => 1,
//                'outputCost' => 1,
//
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'mistral:mistral-small',
//            context: [
//                'label' => 'Mistral Small',
//                'type' => 'mistral',
//                'name' => 'mistral-small-latest',
//                'maxTokens' => 32_000,
//                'contextSize' => 32_000,
//                'inputCost' => 1,
//                'outputCost' => 1,
//
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'mistral:mistral-medium',
//            context: [
//                'label' => 'Mistral Medium',
//                'type' => 'mistral',
//                'name' => 'mistral-medium-latest',
//                'maxTokens' => 32_000,
//                'contextSize' => 32_000,
//                'inputCost' => 1,
//                'outputCost' => 1,
//
//            ],
//        );
//
//        $config->object(
//            class: ModelParams::class,
//            name: 'mistral:mistral-large',
//            context: [
//                'label' => 'Mistral Large',
//                'type' => 'mistral',
//                'name' => 'mistral-large-latest',
//                'maxTokens' => 32_000,
//                'contextSize' => 32_000,
//                'inputCost' => 1,
//                'outputCost' => 1,
//
//            ],
//        );

    }
}