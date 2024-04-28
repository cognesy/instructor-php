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
use Cognesy\Instructor\Core\RequestFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\ResponseDeserializer;
use Cognesy\Instructor\Core\Response\ResponseGenerator;
use Cognesy\Instructor\Core\Response\ResponseTransformer;
use Cognesy\Instructor\Core\Response\ResponseValidator;
use Cognesy\Instructor\Core\ResponseModelFactory;
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

    /// FACTORIES ////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: RequestFactory::class,
        context: [
            'clientFactory' => $config->reference(ApiClientFactory::class),
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
            'responseModelFactory' => $config->reference(ResponseModelFactory::class),
            'events' => $config->reference(EventDispatcher::class),
            'responseGenerator' => $config->reference(CanGenerateResponse::class),
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
