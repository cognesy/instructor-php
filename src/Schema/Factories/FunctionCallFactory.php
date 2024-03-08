<?php
namespace Cognesy\Instructor\Schema\Factories;

use Cognesy\Instructor\Schema\Data\Reference;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;

class FunctionCallFactory {
    private SchemaFactory $schemaFactory;
    private SchemaBuilder $schemaBuilder;
    private ReferenceQueue $references;

    private ?Schema $schema;
    private ?array $jsonSchema;

    public function __construct(
        SchemaFactory $schemaFactory,
        SchemaBuilder $schemaBuilder,
        ReferenceQueue $referenceQueue,
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->schemaBuilder = $schemaBuilder;
        $this->references = $referenceQueue;
    }

    /**
     * Renders function call based on the class
     */
    public function fromClass(
        string $class,
        string $customName = 'extract_object',
        string $customDescription = 'Extract parameters from chat content'
    ) : array {
        $this->schema = $this->schemaFactory->schema($class);
        $this->jsonSchema = $this->schema->toArray($this->onObjectRef(...));
        return $this->render(
            $this->jsonSchema,
            $customName,
            $customDescription
        );
    }

    /**
     * Render function call based on the Schema object
     */
    public function fromSchema(
        Schema $schema,
        string $customName = 'extract_object',
        string $customDescription = 'Extract parameters from chat content'
    ) : array {
        $this->schema = $schema;
        $this->jsonSchema = $schema->toArray($this->onObjectRef(...));
        return $this->render(
            $this->jsonSchema,
            $customName,
            $customDescription
        );
    }

    /**
     * Render function call based on the raw JSON Schema array
     */
    public function fromArray(
        array $jsonSchema,
        string $customName = 'extract_object',
        string $customDescription = 'Extract parameters from chat content'
    ) : array {
        $this->schema = $this->schemaBuilder->fromArray($jsonSchema);
        $this->jsonSchema = $jsonSchema;
        return $this->render(
            $this->jsonSchema,
            $customName,
            $customDescription
        );
    }

    /**
     * Extract the schema model from a function and constructs a function call JSON schema array
     */
    protected function render(
        array $jsonSchema,
        string $name,
        string $description
    ) : array {
        $functionCall = [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
            ]
        ];
        if ($this->references->hasQueued()) {
            $functionCall['function']['parameters']['$defs'] = $this->definitions();
        }
        foreach ($jsonSchema as $key => $value) {
            $functionCall['function']['parameters'][$key] = $value;
        }
        return $functionCall;
    }

    /**
     * Recursive extraction of the schema definitions from the references
     */
    protected function definitions() : array {
        $definitions = [];
        while($this->references->hasQueued()) {
            $reference = $this->references->dequeue();
            if ($reference == null) {
                break;
            }
            $definitions[$reference->classShort] = $this->schemaFactory->schema($reference->class)->toArray($this->onObjectRef(...));
        }
        return array_reverse($definitions);
    }

    /**
     * Callback called when an object reference is found
     */
    private function onObjectRef(Reference $reference) {
        $this->references->queue($reference);
    }
}
