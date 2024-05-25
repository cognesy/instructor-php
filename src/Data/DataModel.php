<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Data\Contracts\HasJsonSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

class DataModel implements HasJsonSchema
{
    private ?string $class;
    private Schema $schema;
    private array $jsonSchema;

    public function __construct(
        string $class = null,
        Schema $schema = null,
        array  $jsonSchema = null,
    ) {
        $this->class = $class;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
    }

    public function class() : ?string {
        // TODO: review if this should be returned from schema class()
        return $this->class;
    }

    public function propertyNames() : array {
        // TODO: assumes schema is ObjectSchema - currently it's always the case, but this needs revision
        return $this->schema->getPropertyNames();
    }

    public function schema() : Schema {
        return $this->schema;
    }

    public function toJsonSchema() : array {
        // TODO: review if this should be returned from schema toArray()
        return $this->jsonSchema;
    }
}