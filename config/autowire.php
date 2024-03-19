<?php
namespace Cognesy\config;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Core\FunctionCallerFactory;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\PartialResponseHandler;
use Cognesy\Instructor\Core\Response\ResponseDeserializer;
use Cognesy\Instructor\Core\Response\ResponseHandler;
use Cognesy\Instructor\Core\Response\ResponseTransformer;
use Cognesy\Instructor\Core\Response\ResponseValidator;
use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\OpenAI\JsonMode\OpenAIJsonCaller;
use Cognesy\Instructor\LLMs\OpenAI\MdJsonMode\OpenAIMdJsonCaller;
use Cognesy\Instructor\LLMs\OpenAI\ToolsMode\OpenAIToolCaller;
use Cognesy\Instructor\Schema\Factories\FunctionCallBuilder;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\PropertyMap;
use Cognesy\Instructor\Schema\SchemaMap;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;
use Cognesy\Instructor\Validators\Symfony\Validator;
use OpenAI;
use OpenAI\Client;

function autowire(Configuration $config) : Configuration
{
    /// CORE ////////////////////////////////////////////////////////////////////////////////////////////////////////

    $config->declare(class: EventDispatcher::class);

    /// LLM CLIENTS /////////////////////////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: Client::class,
        context: [
            'apiKey' => getenv('OPENAI_API_KEY') ?? '',
            'baseUri' => getenv('OPENAI_BASE_URI') ?? '',
            'organization' => getenv('OPENAI_ORGANIZATION') ?? '',
        ],
        getInstance: function($context) {
            return OpenAI::factory()
                ->withApiKey($context['apiKey'])
                ->withOrganization($context['organization'])
                ->withBaseUri($context['baseUri'])
                ->make();
        },
    );

    /// MODE SUPPORT ////////////////////////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: FunctionCallerFactory::class,
        context: [
            'modeHandlers' => [
                Mode::Tools->value => $config->reference(OpenAIToolCaller::class),
                Mode::Json->value => $config->reference(OpenAIJsonCaller::class),
                Mode::MdJson->value => $config->reference(OpenAIMdJsonCaller::class),
            ],
            //'forceMode' => Mode::Tools,
        ]
    );

    $config->declare(
        class: OpenAIToolCaller::class,
        context: [
            'eventDispatcher' => $config->reference(EventDispatcher::class),
            'client' => $config->reference(Client::class),
        ]
    );

    $config->declare(
        class: OpenAIJsonCaller::class,
        context: [
            'eventDispatcher' => $config->reference(EventDispatcher::class),
            'client' => $config->reference(Client::class),
        ]
    );

    $config->declare(
        class: OpenAIMdJsonCaller::class,
        context: [
            'eventDispatcher' => $config->reference(EventDispatcher::class),
            'client' => $config->reference(Client::class),
        ]
    );


    /// SCHEMA MODEL HANDLING ///////////////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: ResponseModelFactory::class,
        context: [
            'functionCallFactory' => $config->reference(FunctionCallBuilder::class),
            'schemaFactory' => $config->reference(SchemaFactory::class),
            'schemaBuilder' => $config->reference(SchemaBuilder::class),
            'typeDetailsFactory' => $config->reference(TypeDetailsFactory::class),
            'eventDispatcher' => $config->reference(EventDispatcher::class),
        ]
    );

    $config->declare(
        class: SchemaFactory::class,
        context: [
            'schemaMap' => $config->reference(SchemaMap::class),
            'propertyMap' => $config->reference(PropertyMap::class),
            'typeDetailsFactory' => $config->reference(TypeDetailsFactory::class),
            'useObjectReferences' => false,
        ]
    );

    $config->declare(
        class: FunctionCallBuilder::class,
        context: [
            'schemaFactory' => $config->reference(SchemaFactory::class),
            'referenceQueue' => $config->reference(ReferenceQueue::class),
        ]
    );

    $config->declare(class: TypeDetailsFactory::class);

    $config->declare(class: SchemaBuilder::class);

    $config->declare(class: SchemaMap::class);

    $config->declare(class: PropertyMap::class);

    $config->declare(class: ReferenceQueue::class);


    /// REQUEST HANDLING ////////////////////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: RequestHandler::class,
        name: CanHandleRequest::class,
        context: [
            'functionCallerFactory' => $config->reference(FunctionCallerFactory::class),
            'responseModelFactory' => $config->reference(ResponseModelFactory::class),
            'eventDispatcher' => $config->reference(EventDispatcher::class),
            'responseHandler' => $config->reference(CanHandleResponse::class),
            'partialResponseHandler' => $config->reference(CanHandlePartialResponse::class),
        ]
    );


    /// RESPONSE HANDLING ///////////////////////////////////////////////////////////////////////////////////////////

    $config->declare(
        class: ResponseHandler::class,
        name: CanHandleResponse::class,
        context: [
            'responseDeserializer' => $config->reference(ResponseDeserializer::class),
            'responseValidator' => $config->reference(ResponseValidator::class),
            'responseTransformer' => $config->reference(ResponseTransformer::class),
        ]
    );

    $config->declare(
        class: PartialResponseHandler::class,
        name: CanHandlePartialResponse::class,
        context: [
            'responseDeserializer' => $config->reference(ResponseDeserializer::class),
            'responseTransformer' => $config->reference(ResponseTransformer::class),
        ]
    );

    $config->declare(
        class: ResponseDeserializer::class,
        context: [
            'deserializer' => $config->reference(CanDeserializeClass::class),
            'eventDispatcher' => $config->reference(EventDispatcher::class),
        ]
    );
    $config->declare(
        class: ResponseValidator::class,
        context: [
            'validator' => $config->reference(CanValidateObject::class),
            'eventDispatcher' => $config->reference(EventDispatcher::class),
        ]
    );

    $config->declare(
        class: ResponseTransformer::class,
        context: [
            'eventDispatcher' => $config->reference(EventDispatcher::class),
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
