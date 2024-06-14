<?php

namespace Cognesy\Instructor\Configs\Clients;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Clients\Gemini\GeminiClient;
use Cognesy\Instructor\Clients\Gemini\GeminiConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Events\EventDispatcher;

class GeminiConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->declare(
            class: GeminiClient::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'connector' => $config->reference(GeminiConnector::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'defaultModel' => $_ENV['GEMINI_DEFAULT_MODEL'] ?? 'gemini-1.5-flash',
                'defaultMaxTokens' => $_ENV['GEMINI_DEFAULT_MAX_TOKENS'] ?? 256,
            ],
            getInstance: function($context) {
                $object = new GeminiClient(
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
            class: GeminiConnector::class,
            context: [
                'apiKey' => $_ENV['GEMINI_API_KEY'] ?? '',
                'baseUrl' => $_ENV['GEMINI_BASE_URI'] ?? '',
                'connectTimeout' => $_ENV['GEMINI_CONNECT_TIMEOUT'] ?? 3,
                'requestTimeout' => $_ENV['GEMINI_REQUEST_TIMEOUT'] ?? 30,
                'metadata' => [],
                'senderClass' => '',
            ],
        );

        $config->declare(
            class: ModelParams::class,
            name: 'google:gemini-1.5-flash',
            context: [
                'label' => 'Google Gemini 1.5 Flash',
                'type' => 'gemini',
                'name' => 'gemini-1.5-flash',
                'maxTokens' => 4096,
                'contextSize' => 128_000,
                'inputCost' => 1,
                'outputCost' => 1,
                'roleMap' => [
                    'user' => 'user',
                    'assistant' => 'model',
                    'system' => 'user'
                ],
            ],
        );

    }
}