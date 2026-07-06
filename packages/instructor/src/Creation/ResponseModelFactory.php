<?php declare(strict_types=1);
namespace Cognesy\Instructor\Creation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Creation\ResponseModel\ClassStringResolver;
use Cognesy\Instructor\Creation\ResponseModel\InstanceResolver;
use Cognesy\Instructor\Creation\ResponseModel\JsonSchemaProviderResolver;
use Cognesy\Instructor\Creation\ResponseModel\JsonSchemaResolver;
use Cognesy\Instructor\Creation\ResponseModel\ResolutionSupport;
use Cognesy\Instructor\Creation\ResponseModel\SchemaObjectResolver;
use Cognesy\Instructor\Creation\ResponseModel\SchemaProviderResolver;
use Cognesy\Instructor\Creation\ResponseModel\ToolSelectionResolver;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuilt;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelRequested;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Turns any supported response-model input shape into a ResponseModel.
 * Dispatch is the single visible match below; each input shape has its own
 * small resolver in Creation/ResponseModel/ sharing ResolutionSupport.
 */
class ResponseModelFactory
{
    protected StructuredOutputConfig $config;
    protected EventDispatcherInterface $events;
    private readonly ResolutionSupport $support;

    public function __construct(
        StructuredOutputSchemaRenderer $schemaRenderer,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->support = new ResolutionSupport($schemaRenderer, $config, $events);
    }

    public function fromAny(string|array|object $requestedModel, ?OutputFormat $outputFormat = null) : ResponseModel {
        $this->events->dispatch(new ResponseModelRequested($this->requestedModelPayload($requestedModel, $outputFormat)));
        $responseModel = $this->buildFrom($requestedModel, $outputFormat);
        $this->events->dispatch(new ResponseModelBuilt($this->builtModelPayload($responseModel)));
        return $responseModel;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function buildFrom(string|array|object $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $support = $this->support;
        return match(true) {
            // can provide JSON schema (class-string or instance)
            (is_string($requestedModel) || is_object($requestedModel)) && is_subclass_of($requestedModel, CanProvideJsonSchema::class)
                => (new JsonSchemaProviderResolver($support))->resolve($requestedModel, $outputFormat),
            // can provide Schema object (class-string or instance)
            (is_string($requestedModel) || is_object($requestedModel)) && is_subclass_of($requestedModel, CanProvideSchema::class)
                => (new SchemaProviderResolver($support))->resolve($requestedModel, $outputFormat),
            // is a Schema object (specifically - ObjectSchema)
            $requestedModel instanceof ObjectSchema
                => (new SchemaObjectResolver($support))->resolve($requestedModel, $outputFormat),
            // is class-string implementing tool selection handling
            is_string($requestedModel) && is_subclass_of($requestedModel, CanHandleToolSelection::class)
                => (new ToolSelectionResolver($support))->resolve($requestedModel, $outputFormat),
            // is string - used as class-string
            is_string($requestedModel)
                => (new ClassStringResolver($support))->resolve($requestedModel, $outputFormat),
            // is empty array - default dynamic structure from config
            is_array($requestedModel) && empty($requestedModel)
                => (new ClassStringResolver($support))->resolve($this->config->outputClass(), $outputFormat),
            // is array - used as JSON Schema
            is_array($requestedModel)
                => (new JsonSchemaResolver($support))->resolve($requestedModel, $outputFormat),
            // must be an object instance at this point
            default
                => (new InstanceResolver($support))->resolve($requestedModel, $outputFormat),
        };
    }

    private function requestedModelPayload(string|array|object $requestedModel, ?OutputFormat $outputFormat) : array
    {
        $payload = [
            'requestedType' => $this->requestedModelType($requestedModel),
            'outputFormatType' => $outputFormat?->type->value,
            'outputClass' => $outputFormat?->targetClass(),
        ];

        if (is_array($requestedModel)) {
            $payload['requestedKeyCount'] = count($requestedModel);
            $payload['requestedKeys'] = array_slice(array_keys($requestedModel), 0, 20);
            return array_filter($payload, fn(mixed $value): bool => $value !== null);
        }

        $payload['requestedClass'] = is_object($requestedModel)
            ? $requestedModel::class
            : ltrim($requestedModel, '\\');

        return array_filter($payload, fn(mixed $value): bool => $value !== null);
    }

    private function builtModelPayload(ResponseModel $responseModel) : array
    {
        $returnedClass = $responseModel->returnedClass();

        return array_filter([
            'responseClass' => $responseModel->instanceClass(),
            'returnedClass' => $returnedClass !== '' ? $returnedClass : null,
            'schemaName' => $responseModel->schemaName(),
            'propertyCount' => count($responseModel->getPropertyNames()),
            'returnTarget' => $responseModel->returnTarget()->value,
            'outputFormatType' => $responseModel->outputFormat()?->type->value,
            'outputClass' => $responseModel->outputFormat()?->targetClass(),
        ], fn(mixed $value): bool => $value !== null);
    }

    private function requestedModelType(string|array|object $requestedModel) : string
    {
        return match (true) {
            is_array($requestedModel) => 'array',
            is_object($requestedModel) => 'object',
            class_exists($requestedModel) => 'class-string',
            default => 'string',
        };
    }
}
