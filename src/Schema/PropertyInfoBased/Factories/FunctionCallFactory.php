<?php
namespace Cognesy\Instructor\Schema\PropertyInfoBased\Factories;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Reference;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Utils\ReferenceQueue;

class FunctionCallFactory {
    private ReferenceQueue $references;
    private ?Schema $schema;
    private ?array $jsonSchema;

    public function __construct() {
        $this->references = new ReferenceQueue;
    }

    /**
     * Renders function call based on the class
     */
    public function fromClass(
        string $class,
        string $customName = 'extract_object',
        string $customDescription = 'Extract parameters from chat content'
    ) : array {
        $this->schema = (new SchemaFactory)->schema($class);
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
        $this->schema = null;
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
                'parameters' => $jsonSchema,
            ]
        ];
        if ($this->references->hasQueued()) {
            $definitions = $this->definitions();
            $functionCall['function']['parameters']['definitions'] = $definitions;
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
            $definitions[$reference->id] = (new SchemaFactory)->schema($reference->class)->toArray($this->onObjectRef(...));
        }
        return $definitions;
    }

    /**
     * Callback called when an object reference is found
     */
    private function onObjectRef(Reference $reference) {
        $this->references->queue($reference);
    }
}
