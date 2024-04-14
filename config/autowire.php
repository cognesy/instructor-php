<?php
namespace Cognesy\config;

use Cognesy\Instructor\ApiClient\CacheConfig;
use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
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
use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Core\RequestBuilder;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\ResponseDeserializer;
use Cognesy\Instructor\Core\Response\ResponseGenerator;
use Cognesy\Instructor\Core\Response\ResponseTransformer;
use Cognesy\Instructor\Core\Response\ResponseValidator;
use Cognesy\Instructor\Core\ResponseModel\ResponseModelFactory;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Core\StreamResponse\PartialsGenerator;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Validation\Symfony\Validator;


function autowire(Configuration $config) : Configuration
{
    /// CORE /////////////////////////////////////////////////////////////////////////////////

    $config->declare(class: EventDispatcher::class);

    /// API CLIENT CACHE /////////////////////////////////////////////////////////////////////

    $config->declare(
        class: CacheConfig::class,
        context: [
            'enabled' => false,
            'expiryInSeconds' => 3600,
            'cacheDir' => '/tmp/instructor/cache',
        ]
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
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
            'events' => $config->reference(EventDispatcher::class),
        ],
    );

    /// LLM CLIENTS //////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: AnthropicClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(AnthropicConnector::class),
        ],
    );

    $config->declare(
        class: AnyscaleClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(AnyscaleConnector::class),
        ],
    );

    $config->declare(
        class: AzureClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(AzureConnector::class),
        ],
    );

    $config->declare(
        class:FireworksAIClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(FireworksAIConnector::class),
        ],
    );

    $config->declare(
        class: GroqClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(GroqConnector::class),
        ],
    );

    $config->declare(
        class: MistralClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(MistralConnector::class),
        ],
    );

    $config->declare(
        class: OpenAIClient::class,
        name: CanCallApi::class, // default client
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(OpenAIConnector::class),
        ],
    );

    $config->declare(
        class: OpenRouterClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(OpenRouterConnector::class),
        ],
    );

    $config->declare(
        class:TogetherAIClient::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'connector' => $config->reference(TogetherAIConnector::class),
        ],
    );

    /// SCHEMA MODEL HANDLING ////////////////////////////////////////////////////////////////

    $config->declare(
        class: ResponseModelFactory::class,
        context: [
            'toolCallBuilder' => $config->reference(ToolCallBuilder::class),
            'schemaFactory' => $config->reference(SchemaFactory::class),
            'events' => $config->reference(EventDispatcher::class),
        ]
    );

    $config->declare(
        class: SchemaFactory::class,
        context: [
            'useObjectReferences' => false,
        ]
    );

    $config->declare(
        class: ToolCallBuilder::class,
        context: [
            'schemaFactory' => $config->reference(SchemaFactory::class),
            'referenceQueue' => $config->reference(ReferenceQueue::class),
        ]
    );

    $config->declare(class: ReferenceQueue::class);


    /// REQUEST HANDLING /////////////////////////////////////////////////////////////////////

    $config->declare(
        class: RequestBuilder::class,
        context: [
            'client' => $config->reference(CanCallApi::class),
            'toolCallBuilder' => $config->reference(ToolCallBuilder::class),
        ]
    );

    $config->declare(
        class: RequestHandler::class,
        name: CanHandleRequest::class,
        context: [
            'responseModelFactory' => $config->reference(ResponseModelFactory::class),
            'events' => $config->reference(EventDispatcher::class),
            'responseGenerator' => $config->reference(CanGenerateResponse::class),
            'requestBuilder' => $config->reference(RequestBuilder::class),
        ]
    );

    $config->declare(
        class: StreamRequestHandler::class,
        name: CanHandleStreamRequest::class,
        context: [
            'responseModelFactory' => $config->reference(ResponseModelFactory::class),
            'events' => $config->reference(EventDispatcher::class),
            'responseGenerator' => $config->reference(CanGenerateResponse::class),
            'partialsGenerator' => $config->reference(CanGeneratePartials::class),
            'requestBuilder' => $config->reference(RequestBuilder::class),
        ]
    );

    /// RESPONSE HANDLING ////////////////////////////////////////////////////////////////////

    $config->declare(
        class: ResponseGenerator::class,
        name: CanGenerateResponse::class,
        context: [
            'responseDeserializer' => $config->reference(ResponseDeserializer::class),
            'responseValidator' => $config->reference(ResponseValidator::class),
            'responseTransformer' => $config->reference(ResponseTransformer::class),
            'events' => $config->reference(EventDispatcher::class),
        ]
    );

    $config->declare(
        class: PartialsGenerator::class,
        name: CanGeneratePartials::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'responseDeserializer' => $config->reference(ResponseDeserializer::class),
            'responseTransformer' => $config->reference(ResponseTransformer::class),
        ]
    );

    $config->declare(
        class: ResponseDeserializer::class,
        context: [
            'deserializer' => $config->reference(CanDeserializeClass::class),
            'events' => $config->reference(EventDispatcher::class),
        ]
    );
    $config->declare(
        class: ResponseValidator::class,
        context: [
            'validator' => $config->reference(CanValidateObject::class),
            'events' => $config->reference(EventDispatcher::class),
        ]
    );

    $config->declare(
        class: ResponseTransformer::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
        ]
    );

    $config->declare(
        class: Deserializer::class,
        name: CanDeserializeClass::class,
    );

    $config->declare(
        class: Validator::class,
        name: CanValidateObject::class,
    );

    return $config;
}
