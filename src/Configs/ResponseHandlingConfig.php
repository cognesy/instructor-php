<?php

namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\ResponseGenerator;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Core\StreamResponse\PartialsGenerator;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Deserialization\Symfony\SymfonyDeserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Symfony\SymfonyValidator;

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
                'events' => $config->reference(EventDispatcher::class),
                'deserializers' => $config->referenceList([
                    CanDeserializeClass::class,
                ]),
            ]
        );
        $config->declare(
            class: SymfonyDeserializer::class,
            name: CanDeserializeClass::class,
        );

        $config->declare(
            class: ResponseValidator::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'validators' => $config->referenceList([
                    SymfonyValidator::class,
                ]),
            ]
        );

        $config->declare(
            class: SymfonyValidator::class,
        );

        $config->declare(
            class: ResponseTransformer::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'transformers' => [],
            ]
        );
    }
}