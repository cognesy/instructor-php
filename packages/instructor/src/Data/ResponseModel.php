<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class ResponseModel implements CanProvideJsonSchema
{
    use \Cognesy\Instructor\Data\Traits\ResponseModel\HandlesToolCalls;
    use \Cognesy\Instructor\Data\Traits\ResponseModel\HandlesInstance;
    use \Cognesy\Instructor\Data\Traits\ResponseModel\HandlesSchema;

    private string $class;
    private Schema $schema;
    private array $jsonSchema;

    public function __construct(
        string $class,
        mixed  $instance,
        Schema $schema,
        array  $jsonSchema,
        ?ToolCallBuilder $toolCallBuilder = null,
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
