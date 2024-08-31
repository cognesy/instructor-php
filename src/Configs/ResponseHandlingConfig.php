<?php

namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Container\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Core\RawRequestHandler;
use Cognesy\Instructor\Core\RawStreamRequestHandler;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\Response\ResponseGenerator;
use Cognesy\Instructor\Core\StreamRequestHandler;
use Cognesy\Instructor\Core\StreamResponse\PartialsGenerator;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;

class ResponseHandlingConfig implements CanAddConfiguration
{
    public function addConfiguration(Container $config): void {

        $config->object(
            class: RawRequestHandler::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
            ]
        );

        $config->object(
            class: RawStreamRequestHandler::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
            ]
        );

        $config->object(
            class: RequestHandler::class,
            name: CanHandleRequest::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'responseGenerator' => $config->reference(CanGenerateResponse::class),
            ]
        );

        $config->object(
            class: ResponseGenerator::class,
            name: CanGenerateResponse::class,
            context: [
                'responseDeserializer' => $config->reference(ResponseDeserializer::class),
                'responseValidator' => $config->reference(ResponseValidator::class),
                'responseTransformer' => $config->reference(ResponseTransformer::class),
                'events' => $config->reference(EventDispatcher::class),
            ]
        );

        $config->object(
            class: StreamRequestHandler::class,
            name: CanHandleStreamRequest::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'responseGenerator' => $config->reference(CanGenerateResponse::class),
                'partialsGenerator' => $config->reference(CanGeneratePartials::class),
            ]
        );

        $config->object(
            class: PartialsGenerator::class,
            name: CanGeneratePartials::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'responseDeserializer' => $config->reference(ResponseDeserializer::class),
                'responseTransformer' => $config->reference(ResponseTransformer::class),
            ]
        );

        $config->object(
            class: ResponseDeserializer::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'deserializers' => $config->reference('deserializers'),
            ]
        );

        $config->object(
            class: ResponseValidator::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'validators' => $config->reference('validators'),
            ]
        );

        $config->object(
            class: ResponseTransformer::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'transformers' => $config->reference('transformers'),
            ]
        );

        $config->value(
            name: 'validators',
            value: [
                SymfonyValidator::class,
            ],
        );

        $config->value(
            name: 'deserializers',
            value: [
                SymfonyDeserializer::class,
            ],
        );

        $config->value(
            name: 'transformers',
            value: [],
        );
    }
}