<?php
namespace Cognesy\Instructor\Features\Core\Data;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;

class ResponseModel implements CanProvideJsonSchema
{
    use Traits\ResponseModel\HandlesToolCalls;
    use Traits\ResponseModel\HandlesInstance;
    use Traits\ResponseModel\HandlesSchema;

    private string $class;
    private Schema $schema;
    private array $jsonSchema;

    public function __construct(
        string $class,
        mixed  $instance,
        Schema $schema,
        array  $jsonSchema,
        ToolCallBuilder $toolCallBuilder = null,
    ) {
        $this->class = $class;
        $this->instance = $instance;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
        $this->toolCallBuilder = $toolCallBuilder;
    }

    public function instanceClass() : string {
        return $this->class;
    }

    public function returnedClass() : string {
        return $this->schema->typeDetails->class;
    }
}
