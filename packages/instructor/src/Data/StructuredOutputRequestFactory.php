<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;
use Cognesy\Utils\Events\Contracts\EventListenerInterface;
use Cognesy\Utils\Events\EventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

class StructuredOutputRequestFactory
{
    private StructuredOutputConfig $config;
    private EventDispatcherInterface $events;
    private EventListenerInterface $listener;

    public function __construct(
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
        EventListenerInterface $listener
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->listener = $listener;
    }

    public function withConfig(StructuredOutputConfig $config) : static {
        $this->config = $config;
        return $this;
    }

    public function fromBuilder(StructuredOutputRequestBuilder $requestBuilder) : StructuredOutputRequest {
        return $requestBuilder
            ->withConfig($this->config)
            ->withResponseModel($this->makeResponseModel(
                $requestBuilder->requestedSchema(),
                $this->config))
            ->build();
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    /**
     * Extracted ResponseModel creation logic from HandlesInvocation
     */
    private function makeResponseModel(
        string|array|object $requestedSchema,
        StructuredOutputConfig $config,
    ): ResponseModel {
        $schemaFactory = new SchemaFactory($config->useObjectReferences());

        // Handle event dispatcher defaults
        if (is_null($this->events) || is_null($this->listener)) {
            $default = new EventDispatcher();
        }

        $responseModelFactory = new ResponseModelFactory(
            new ToolCallBuilder($schemaFactory, new ReferenceQueue()),
            $schemaFactory,
            $this->events ?? $default,
            $this->listener ?? $default,
        );

        return $responseModelFactory->fromAny(
            $requestedSchema,
            $config->toolName(),
            $config->toolDescription()
        );
    }
}