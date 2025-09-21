<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class ResponseModel implements CanProvideJsonSchema
{
    private string $class;
    private Schema $schema;
    private array $jsonSchema;
    private string $toolName;
    private string $toolDescription;
    private string $schemaName;
    private string $schemaDescription;
    private bool $useObjectReferences = false;

    public function __construct(
        string $class,
        mixed  $instance,
        Schema $schema,
        array  $jsonSchema,
        string $schemaName,
        string $schemaDescription,
        string $toolName,
        string $toolDescription,
        bool   $useObjectReferences = false,
    ) {
        $this->class = $class;
        $this->instance = $instance;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
        $this->schemaName = $schemaName;
        $this->schemaDescription = $schemaDescription;
        $this->toolName = $toolName;
        $this->toolDescription = $toolDescription;
        $this->useObjectReferences = $useObjectReferences;
    }

    public function instanceClass() : string {
        return $this->class;
    }

    public function returnedClass() : string {
        return $this->schema->typeDetails->class;
    }

    public function toArray() : array {
        return [
            'class' => $this->class,
            'instance' => get_object_vars($this->instance),
            'schema' => $this->schema->toArray(),
            'jsonSchema' => $this->jsonSchema,
            'schemaName' => $this->schemaName,
            'schemaDescription' => $this->schemaDescription,
        ];
    }

    public function clone() : self {
        return new self(
            class: $this->class,
            instance: clone $this->instance,
            schema: $this->schema->clone(),
            jsonSchema: $this->jsonSchema,
            schemaName: $this->schemaName,
            schemaDescription: $this->schemaDescription,
            toolName: $this->toolName,
            toolDescription: $this->toolDescription,
            useObjectReferences: $this->useObjectReferences,
        );
    }

    // HANDLES INSTANCE ////////////////////////////////////////////////

    private mixed $instance;

    public function instance() : mixed {
        return $this->instance;
    }

    public function withInstance(mixed $instance) : static {
        $this->instance = $instance;
        return $this;
    }

    // HANDLES SCHEMA ////////////////////////////////////////////////

    public function schemaName() : string {
        return $this->schemaName ?? $this->schema()->name();
    }

    public function schema() : Schema {
        return $this->schema;
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return $this->schema()->getPropertyNames();
    }

    /** @return array<string, mixed> */
    public function getPropertyValues() : array {
        $values = [];
        foreach ($this->getPropertyNames() as $name) {
            $values[$name] = match(true) {
                isset($this->instance->$name) => $this->instance->$name,
                default => null,
            };
        }
        return $values;
    }

    /** @param array<string, mixed> $values */
    public function setPropertyValues(array $values) : void {
        foreach ($values as $name => $value) {
            if (property_exists($this->instance, $name)) {
                $this->instance->$name = $value;
            }
        }
    }

    public function toJsonSchema() : array {
        // TODO: this can be computed from schema
        return $this->jsonSchema;
    }

    // HANDLES TOOL CALLS ////////////////////////////////////////////////

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
        $schemaFactory = new SchemaFactory(useObjectReferences: $this->useObjectReferences);
        $toolCallBuilder = new ToolCallBuilder($schemaFactory);

        return match(true) {
            $this->instance() instanceof CanHandleToolSelection => $this->instance()->toToolCallsJson(),
            default => $toolCallBuilder->renderToolCall(
                $this->toJsonSchema(),
                $this->toolName,
                $this->toolDescription
            ),
        };
    }
}
