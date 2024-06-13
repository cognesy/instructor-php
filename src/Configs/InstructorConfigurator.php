<?php
namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\RequestConfig\CacheConfig;
use Cognesy\Instructor\ApiClient\RequestConfig\DebugConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Configurator;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
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
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Symfony\Validator;

class InstructorConfigurator extends Configurator
{
    public function setup(Configuration $config) : void {
        ClientConfigurator::addTo($config);

        $config->external(
            class: EventDispatcher::class,
            reference: $this->context(EventDispatcher::class),
        );

        //$config->declare(
        //    class: Logger::class,
        //    name: LoggerInterface::class,
        //    context: [
        //        'name' => 'instructor',
        //        'handlers' => [
        //            new StreamHandler('php://stdout', Level::Debug)
        //        ],
        //        'processors' => [],
        //        'timezone' => new DateTimeZone('UTC'),
        //    ],
        //);
        //
        //$config->declare(
        //    class: EventLogger::class,
        //    context: [
        //        'logger' => $config->reference(LoggerInterface::class),
        //        'level' => LogLevel::INFO,
        //    ],
        //);

        /// CONTEXT //////////////////////////////////////////////////////////////////////////////

        $config->declare(
            class: ApiRequestConfig::class,
            context: [
                'cacheConfig' => $config->reference(CacheConfig::class),
                'debugConfig' => $config->reference(DebugConfig::class),
                'events' => $config->reference(EventDispatcher::class),
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
            class: DebugConfig::class,
            context: [
                'debug' => false,
                'stopOnDebug' => false,
                'forceDebug' => false,
            ]
        );

        /// FACTORIES ////////////////////////////////////////////////////////////////////////////

        $config->declare(
            class: RequestFactory::class,
            context: [
                'clientFactory' => $config->reference(ApiClientFactory::class),
                'responseModelFactory' => $config->reference(ResponseModelFactory::class),
                'modelFactory' => $config->reference(ModelFactory::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
                'requestConfig' => $config->reference(ApiRequestConfig::class),
                'events' => $config->reference(EventDispatcher::class),
            ],
        );

        $config->declare(
            class: ApiRequestFactory::class,
            context: [
                'requestConfig' => $config->reference(ApiRequestConfig::class),
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
    }
}