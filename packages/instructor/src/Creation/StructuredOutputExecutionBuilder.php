<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;

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
                outputFormat: $request->outputFormat(),
            ),
        );
    }

    private function makeResponseModel(
        string|array|object $requestedSchema,
        StructuredOutputConfig $config,
        CanHandleEvents $events,
        ?OutputFormat $outputFormat = null,
    ): ResponseModel {
        $schemaRenderer = new StructuredOutputSchemaRenderer($config);
        $responseModelFactory = new ResponseModelFactory(
            schemaRenderer: $schemaRenderer,
            config: $config,
            events: $events,
        );
        return $responseModelFactory->fromAny($requestedSchema, $outputFormat);
    }
}
