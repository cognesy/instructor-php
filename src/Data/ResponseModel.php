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

    private string $toolName = '';
    private string $toolDescription = '';

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

    public function toolName() : string {
        return $this->toolName;
    }

    public function withToolName(string $toolName) : static {
        $this->toolName = $toolName;
        return $this;
    }

    public function toolDescription() : string {
        return $this->toolDescription;
    }

    public function withToolDescription(string $toolDescription) : static {
        $this->toolDescription = $toolDescription;
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
            $requestedModel['name'] ?? $this->toolName,
            $requestedModel['description'] ?? $this->toolDescription
        );
    }

    public function schema() : Schema {
        return $this->schema;
    }
}
