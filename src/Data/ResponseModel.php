<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;

class ResponseModel
{
    private ToolCallBuilder $toolCallBuilder;

    private mixed $instance;
    private DataModel $dataModel;
    private string $toolName = '';
    private string $toolDescription = '';

    public function __construct(
        string $class = null,
        mixed  $instance = null,
        Schema $schema = null,
        array  $jsonSchema = null,
        ToolCallBuilder $toolCallBuilder = null,
    ) {
        $this->instance = $instance;
        $this->toolCallBuilder = $toolCallBuilder;
        $this->dataModel = new DataModel($class, $schema, $jsonSchema);
    }

    public function instance() : mixed {
        return $this->instance;
    }

    public function withInstance(mixed $instance) : static {
        $this->instance = $instance;
        return $this;
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

    public function toolCallSchema() : array {
        return match(true) {
            $this->instance() instanceof CanHandleToolSelection => $this->instance()->toToolCallsJson(),
            default => $this->toolCallBuilder->renderToolCall(
                $this->toJsonSchema(),
                $this->toolName,
                $this->toolDescription
            ),
        };
    }

    public function class() : ?string {
        return $this->dataModel->class();
    }

    public function propertyNames() : array {
        return $this->dataModel->schema()->getPropertyNames();
    }

    public function schema() : Schema {
        return $this->dataModel->schema();
    }

    public function toJsonSchema() : array {
        return $this->dataModel->toJsonSchema();
    }
}
