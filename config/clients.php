<?php

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Anthropic\AnthropicConnector;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleConnector;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Azure\AzureConnector;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIConnector;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Groq\GroqConnector;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Mistral\MistralConnector;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIConnector;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterConnector;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIConnector;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;

return function(Configuration $config) : Configuration
{
    /// LLM CLIENT FACTORY ///////////////////////////////////////////////////////////////////

    $config->declare(
        class: ApiClientFactory::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultClient' => $config->reference(CanCallApi::class),
            'clients' => [
                'anthropic' => $config->reference(AnthropicClient::class),
                'anyscale' => $config->reference(AnyscaleClient::class),
                'azure' => $config->reference(AzureClient::class),
                'fireworks' => $config->reference(FireworksAIClient::class),
                'groq' => $config->reference(GroqClient::class),
                'mistral' => $config->reference(MistralClient::class),
                'openai' => $config->reference(OpenAIClient::class),
                'openrouter' => $config->reference(OpenRouterClient::class),
                'together' => $config->reference(TogetherAIClient::class),
            ],
        ],
    );

    /// LLM CLIENTS //////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: AnthropicClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(AnthropicConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'claude-3-haiku-20240307',
            'defaultMaxTokens' => 256,
        ],
        getInstance: function($context) {
            $object = new AnthropicClient(
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
        class:FireworksAIClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(FireworksAIConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'accounts/fireworks/models/mixtral-8x7b-instruct',
            'defaultMaxTokens' => 256,
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

    $config->declare(
        class: GroqClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(GroqConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'llama3-8b-8192',
            'defaultMaxTokens' => 256,
        ],
        getInstance: function($context) {
            $object = new GroqClient(
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
        class: MistralClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(MistralConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'mistral-small-latest',
            'defaultMaxTokens' => 256,
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

    $config->declare(
        class: OpenAIClient::class,
        name: CanCallApi::class, // default client
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(OpenAIConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'gpt-4-turbo',
            'defaultMaxTokens' => 256,
        ],
        getInstance: function($context) {
            $object = new OpenAIClient(
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
        class: OpenRouterClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(OpenRouterConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'gpt-3.5-turbo',
            'defaultMaxTokens' => 256,
        ],
        getInstance: function($context) {
            $object = new OpenRouterClient(
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
        class: TogetherAIClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(TogetherAIConnector::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'defaultModel' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',
            'defaultMaxTokens' => 256,
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

    /// API CONNECTORS ///////////////////////////////////////////////////////////////////////

    $config->declare(
        class: AnthropicConnector::class,
        context: [
            'apiKey' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
            'baseUrl' => $_ENV['ANTHROPIC_BASE_URI'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
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
        class:FireworksAIConnector::class,
        context: [
            'apiKey' => $_ENV['FIREWORKSAI_API_KEY'] ?? '',
            'baseUrl' => $_ENV['FIREWORKSAI_BASE_URI'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
    );

    $config->declare(
        class: GroqConnector::class,
        context: [
            'apiKey' => $_ENV['GROQ_API_KEY'] ?? '',
            'baseUrl' => $_ENV['GROQ_BASE_URI'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
    );

    $config->declare(
        class: MistralConnector::class,
        context: [
            'apiKey' => $_ENV['MISTRAL_API_KEY'] ?? '',
            'baseUrl' => $_ENV['MISTRAL_BASE_URI'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
    );

    $config->declare(
        class: OpenAIConnector::class,
        context: [
            'apiKey' => $_ENV['OPENAI_API_KEY'] ?? '',
            'baseUrl' => $_ENV['OPENAI_BASE_URI'] ?? '',
            'organization' => $_ENV['OPENAI_ORGANIZATION'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
    );

    $config->declare(
        class: OpenRouterConnector::class,
        context: [
            'apiKey' => $_ENV['OPENROUTER_API_KEY'] ?? '',
            'baseUrl' => $_ENV['OPENROUTER_BASE_URI'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
    );

    $config->declare(
        class:TogetherAIConnector::class,
        context: [
            'apiKey' => $_ENV['TOGETHERAI_API_KEY'] ?? '',
            'baseUrl' => $_ENV['TOGETHERAI_BASE_URI'] ?? '',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'metadata' => [],
            'senderClass' => '',
        ],
    );

    return $config;
};
