<?php
namespace Cognesy\config;

use Cognesy\Instructor\ApiClient\CacheConfig;
use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Core\Factories\ModelFactory;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\ResponseDeserializer;
use Cognesy\Instructor\Core\Response\ResponseGenerator;
use Cognesy\Instructor\Core\Response\ResponseTransformer;
use Cognesy\Instructor\Core\Response\ResponseValidator;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Core\StreamResponse\PartialsGenerator;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Validation\Symfony\Validator;

function autowire(
    Configuration $config,
    EventDispatcher $events
) : Configuration
{
    /// INCLUDES /////////////////////////////////////////////////////////////////////////////

    $config->include('clients.php');
    $config->include('models/anthropic.php');
    $config->include('models/anyscale.php');
    $config->include('models/azure.php');
    $config->include('models/fireworks.php');
    $config->include('models/groq.php');
    $config->include('models/mistral.php');
    $config->include('models/ollama.php');
    $config->include('models/openai.php');
    $config->include('models/openrouter.php');
    $config->include('models/together.php');

    /// CORE /////////////////////////////////////////////////////////////////////////////////

    $config->external(
        class: EventDispatcher::class,
        reference: $events
    );

    /// CONTEXT //////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: ApiRequestContext::class,
        context: [
            'cacheConfig' => $config->reference(CacheConfig::class),
        ],
    );

    $config->declare(
        class: CacheConfig::class,
        context: [
            'enabled' => false,
            'expiryInSeconds' => 3600,
            'cachePath' => '/tmp/instructor/cache',
        ]
    );

    $config->declare(
        class: ModelFactory::class,
        context: [
            'models' => [
                'anthropic:claude-3-haiku' => $config->reference('anthropic:claude-3-haiku'),
                'anthropic:claude-3-sonnet' => $config->reference('anthropic:claude-3-sonnet'),
                'anthropic:claude-3-opus' => $config->reference('anthropic:claude-3-opus'),
                'anyscale:mixtral-8x7b' => $config->reference('anyscale:mixtral-8x7b'),
                'azure:gpt-3.5-turbo' => $config->reference('azure:gpt-3.5-turbo'),
                'fireworks:mixtral-8x7b' => $config->reference('fireworks:mixtral-8x7b'),
                'groq:llama3-8b' => $config->reference('groq:llama3-8b'),
                'groq:llama3-70b' => $config->reference('groq:llama3-70b'),
                'groq:mixtral-8x7b' => $config->reference('groq:mixtral-8x7b'),
                'groq:gemma-7b' => $config->reference('groq:gemma-7b'),
                'mistral:mistral-7b' => $config->reference('mistral:mistral-7b'),
                'mistral:mixtral-8x7b' => $config->reference('mistral:mixtral-8x7b'),
                'mistral:mixtral-8x22b' => $config->reference('mistral:mixtral-8x22b'),
                'mistral:mistral-small' => $config->reference('mistral:mistral-small'),
                'mistral:mistral-medium' => $config->reference('mistral:mistral-medium'),
                'mistral:mistral-large' => $config->reference('mistral:mistral-large'),
                'ollama:llama2' => $config->reference('ollama:llama2'),
                'openai:gpt-4o' => $config->reference('openai:gpt-4o'),
                'openai:gpt-4-turbo' => $config->reference('openai:gpt-4-turbo'),
                'openai:gpt-4' => $config->reference('openai:gpt-4'),
                'openai:gpt-4-32k' => $config->reference('openai:gpt-4-32k'),
                'openai:gpt-3.5-turbo' => $config->reference('openai:gpt-3.5-turbo'),
                'openrouter:llama3' => $config->reference('openrouter:llama3'),
                'openrouter:mixtral-8x7b' => $config->reference('openrouter:mixtral-8x7b'),
                'openrouter:mistral-7b' => $config->reference('openrouter:mistral-7b'),
                'together:mixtral-8x7b' => $config->reference('together:mixtral-8x7b'),
            ],
            'allowUnknownModels' => false,
        ],
    );

    /// FACTORIES ////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: RequestFactory::class,
        context: [
            'clientFactory' => $config->reference(ApiClientFactory::class),
            'responseModelFactory' => $config->reference(ResponseModelFactory::class),
            'modelFactory' => $config->reference(ModelFactory::class),
            'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            'events' => $config->reference(EventDispatcher::class),
        ],
    );

    $config->declare(
        class: ApiRequestFactory::class,
        context: [
            'context' => $config->reference(ApiRequestContext::class),
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
        class: ToolCallBuilder::class,
        context: [
            'schemaFactory' => $config->reference(SchemaFactory::class),
            'referenceQueue' => $config->reference(ReferenceQueue::class),
        ]
    );

    $config->declare(
        class: SchemaFactory::class,
        context: [
            'useObjectReferences' => false,
        ]
    );

    $config->declare(class: ReferenceQueue::class);


    /// REQUEST HANDLING /////////////////////////////////////////////////////////////////////

    $config->declare(
        class: RequestHandler::class,
        name: CanHandleRequest::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'responseGenerator' => $config->reference(CanGenerateResponse::class),
        ]
    );

    $config->declare(
        class: StreamRequestHandler::class,
        name: CanHandleStreamRequest::class,
        context: [
            'events' => $config->reference(EventDispatcher::class),
            'responseGenerator' => $config->reference(CanGenerateResponse::class),
            'partialsGenerator' => $config->reference(CanGeneratePartials::class),
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
