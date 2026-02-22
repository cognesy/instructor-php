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
    private ?ResponseModelFactory $responseModelFactory;
    private ?int $factoryConfigId;
    private bool $usesInjectedFactory;

    public function __construct(
        private readonly CanHandleEvents $events,
        ?ResponseModelFactory $responseModelFactory = null,
    ) {
        $this->responseModelFactory = $responseModelFactory;
        $this->factoryConfigId = null;
        $this->usesInjectedFactory = $responseModelFactory !== null;
    }

    public function createWith(
        StructuredOutputRequest $request,
        StructuredOutputConfig $config,
    ) : StructuredOutputExecution {
        return new StructuredOutputExecution(
            request: $request,
            config: $config,
            responseModel: $this->makeResponseModel(
                requestedSchema: $request->requestedSchema(),
                factory: $this->responseModelFactoryFor($config),
                outputFormat: $request->outputFormat(),
            ),
        );
    }

    private function makeResponseModel(
        string|array|object $requestedSchema,
        ResponseModelFactory $factory,
        ?OutputFormat $outputFormat = null,
    ): ResponseModel {
        return $factory->fromAny($requestedSchema, $outputFormat);
    }

    private function responseModelFactoryFor(StructuredOutputConfig $config) : ResponseModelFactory {
        if ($this->usesInjectedFactory && $this->responseModelFactory !== null) {
            return $this->responseModelFactory;
        }
        $configId = spl_object_id($config);
        if ($this->factoryConfigId === $configId && $this->responseModelFactory !== null) {
            return $this->responseModelFactory;
        }
        $this->responseModelFactory = new ResponseModelFactory(
            schemaRenderer: new StructuredOutputSchemaRenderer($config),
            config: $config,
            events: $this->events,
        );
        $this->factoryConfigId = $configId;
        return $this->responseModelFactory;
    }
}
