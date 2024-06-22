<?php

namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\RequestConfig\CacheConfig;
use Cognesy\Instructor\ApiClient\RequestConfig\DebugConfig;
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

        $config->object(
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

        $config->object(
            class: ResponseModelFactory::class,
            context: [
                'toolCallBuilder' => $config->reference(ToolCallBuilder::class),
                'schemaFactory' => $config->reference(SchemaFactory::class),
                'events' => $config->reference(EventDispatcher::class),
            ]
        );

        $config->object(
            class: ToolCallBuilder::class,
            context: [
                'schemaFactory' => $config->reference(SchemaFactory::class),
                'referenceQueue' => $config->reference(ReferenceQueue::class),
            ]
        );

        $config->object(
            class: SchemaFactory::class,
            context: [
                'useObjectReferences' => $_ENV['INSTRUCTOR_USE_OBJECT_REFERENCES'] ?? false,
            ]
        );

        $config->object(class: ReferenceQueue::class);

        $config->object(
            class: ApiRequestConfig::class,
            context: [
                'cacheConfig' => $config->reference(CacheConfig::class),
                'debugConfig' => $config->reference(DebugConfig::class),
                'events' => $config->reference(EventDispatcher::class),
            ],
        );

        $config->object(
            class: CacheConfig::class,
            context: [
                'enabled' => $_ENV['INSTRUCTOR_CACHE_ENABLED'] ?? false,
                'expiryInSeconds' => $_ENV['INSTRUCTOR_CACHE_EXPIRY'] ?? 3600,
                'cachePath' => $_ENV['INSTRUCTOR_CACHE_PATH'] ?? '/tmp/instructor/cache',
            ]
        );

        $config->object(
            class: DebugConfig::class,
            context: [
                'debug' => $_ENV['INSTRUCTOR_DEBUG'] ?? false,
                'stopOnDebug' => $_ENV['INSTRUCTOR_STOP_ON_DEBUG'] ?? false,
                'forceDebug' => $_ENV['INSTRUCTOR_FORCE_DEBUG'] ?? false,
            ]
        );

    }
}