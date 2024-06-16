<?php

namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Core\Factories\ModelFactory;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;

class RequestHandlingConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

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
                'useObjectReferences' => $_ENV['INSTRUCTOR_USE_OBJECT_REFERENCES'] ?? false,
            ]
        );

        $config->declare(class: ReferenceQueue::class);

    }
}