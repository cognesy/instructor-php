<?php

namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
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
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Symfony\Validator;

class ResponseHandlingConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        $config->declare(
            class: RequestHandler::class,
            name: CanHandleRequest::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'responseGenerator' => $config->reference(CanGenerateResponse::class),
            ]
        );

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
            class: StreamRequestHandler::class,
            name: CanHandleStreamRequest::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'responseGenerator' => $config->reference(CanGenerateResponse::class),
                'partialsGenerator' => $config->reference(CanGeneratePartials::class),
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
            class: Deserializer::class,
            name: CanDeserializeClass::class,
        );

        $config->declare(
            class: ResponseValidator::class,
            context: [
                'validator' => $config->reference(CanValidateObject::class),
                'events' => $config->reference(EventDispatcher::class),
            ]
        );

        $config->declare(
            class: Validator::class,
            name: CanValidateObject::class,
        );

        $config->declare(
            class: ResponseTransformer::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
            ]
        );

    }
}