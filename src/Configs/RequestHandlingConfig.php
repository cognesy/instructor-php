<?php
namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\RequestConfig\CacheConfig;
use Cognesy\Instructor\ApiClient\RequestConfig\DebugConfig;
use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Container\Contracts\CanAddConfiguration;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Utils\Settings;

class RequestHandlingConfig implements CanAddConfiguration
{
    public function addConfiguration(Container $config): void {
        $config->object(
            class: ApiRequestFactory::class,
            context: [
                'requestConfig' => $config->reference(ApiRequestConfig::class),
            ],
        );

        $config->object(
            class: ApiClientFactory::class,
            context: [
                'events' => $config->reference(EventDispatcher::class),
                'apiRequestFactory' => $config->reference(ApiRequestFactory::class),
            ],
        );

        $config->object(
            class: RequestFactory::class,
            context: [
                'clientFactory' => $config->reference(ApiClientFactory::class),
                'responseModelFactory' => $config->reference(ResponseModelFactory::class),
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
                'useObjectReferences' => Settings::get('llm', 'useObjectReferences') ?? false,
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
                'enabled' => Settings::get('llm', 'cache.enabled', false),
                'expiryInSeconds' => Settings::get('llm', 'cache.expiryInSeconds', 3600),
                'cachePath' => Settings::get('llm', 'cache.path', '/tmp/instructor/cache'),
            ]
        );

        $config->object(
            class: DebugConfig::class,
            context: [
                'debug' => Settings::get('llm', 'debug.enabled', false),
                'stopOnDebug' => Settings::get('llm', 'debug.stopOnDebug', false),
                'forceDebug' => Settings::get('llm', 'debug.forceDebug', false),
            ]
        );

    }
}