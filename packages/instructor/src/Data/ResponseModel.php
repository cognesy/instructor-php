<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data;

use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class ResponseModel implements CanProvideJsonSchema
{
    use Traits\ResponseModel\HandlesToolCalls;
    use Traits\ResponseModel\HandlesInstance;
    use Traits\ResponseModel\HandlesSchema;

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
}
