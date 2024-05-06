<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;

class ResponseModel
{
    private ToolCallBuilder $toolCallBuilder;

    private mixed $instance;
    private ?string $class;
    private Schema $schema;
    private array $jsonSchema;

    private string $functionName = '';
    private string $functionDescription = '';

    public function __construct(
        string $class = null,
        mixed  $instance = null,
        Schema $schema = null,
        array  $jsonSchema = null,
        ToolCallBuilder $toolCallBuilder = null,
    ) {
        $this->class = $class;
        $this->instance = $instance;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
        $this->toolCallBuilder = $toolCallBuilder;
    }

    public function functionName() : string {
        return $this->functionName;
    }

    public function withFunctionName(string $functionName) : static {
        $this->functionName = $functionName;
        return $this;
    }

    public function functionDescription() : string {
        return $this->functionDescription;
    }

    public function withFunctionDescription(string $functionDescription) : static {
        $this->functionDescription = $functionDescription;
        return $this;
    }

    public function jsonSchema() : array {
        return $this->jsonSchema;
    }

    public function instance() : mixed {
        return $this->instance;
    }

    public function withInstance(mixed $instance) : static {
        $this->instance = $instance;
        return $this;
    }

    public function class() : ?string {
        return $this->class;
    }

    public function propertyNames() : array {
        return $this->schema->getPropertyNames();
    }

    public function toolCallSchema() : array {
        return $this->toolCallBuilder->render(
            $this->jsonSchema,
            $requestedModel['name'] ?? $this->functionName,
            $requestedModel['description'] ?? $this->functionDescription
        );
    }

    public function schema() : Schema {
        return $this->schema;
    }
}
