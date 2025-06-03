<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class ResponseModel implements CanProvideJsonSchema
{
    use Traits\ResponseModel\HandlesToolCalls;
    use Traits\ResponseModel\HandlesInstance;
    use Traits\ResponseModel\HandlesSchema;

    private string $class;
    private Schema $schema;
    private array $jsonSchema;
    private string $schemaName;

    public function __construct(
        string $class,
        mixed  $instance,
        Schema $schema,
        array  $jsonSchema,
        string $schemaName,
        ?ToolCallBuilder $toolCallBuilder = null,
    ) {
        $this->class = $class;
        $this->instance = $instance;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
        $this->toolCallBuilder = $toolCallBuilder;
        $this->schemaName = $schemaName;
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
        ];
    }
}
