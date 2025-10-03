<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class StructuredOutputExecutionBuilder
{
    public function __construct(
        private readonly CanHandleEvents $events,
    ) {}

    public function createWith(
        StructuredOutputRequest $request,
        StructuredOutputConfig $config,
    ) : StructuredOutputExecution {
        return new StructuredOutputExecution(
            request: $request,
            config: $config,
            responseModel: $this->makeResponseModel(
                requestedSchema: $request->requestedSchema(),
                config: $config,
                events: $this->events,
            ),
        );
    }

    private function makeResponseModel(
        string|array|object $requestedSchema,
        StructuredOutputConfig $config,
        CanHandleEvents $events,
    ): ResponseModel {
        $schemaFactory = new SchemaFactory(
            useObjectReferences: $config->useObjectReferences(),
            schemaConverter: new JsonSchemaToSchema(
                defaultToolName: $config->toolName(),
                defaultToolDescription: $config->toolDescription(),
                defaultOutputClass: $config->outputClass(),
            )
        );
        $toolCallBuilder = new ToolCallBuilder($schemaFactory);
        $responseModelFactory = new ResponseModelFactory(
            toolCallBuilder: $toolCallBuilder,
            schemaFactory: $schemaFactory,
            config: $config,
            events: $events,
        );
        return $responseModelFactory->fromAny($requestedSchema);
    }
}