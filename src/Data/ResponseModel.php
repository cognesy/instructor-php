<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;

class ResponseModel
{
    private ToolCallBuilder $toolCallBuilder;

    public mixed $instance;
    public ?string $class;
    public Schema $schema;
    public array $jsonSchema;

    public string $functionName = '';
    public string $functionDescription = '';

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

    public function jsonSchema() : array {
        return $this->jsonSchema;
    }

    public function toolCallSchema() : array {
        return $this->toolCallBuilder->render(
            $this->jsonSchema,
            $requestedModel['name'] ?? $this->functionName,
            $requestedModel['description'] ?? $this->functionDescription
        );
    }
}