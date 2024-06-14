<?php
namespace Cognesy\Instructor\Schema\Factories;

use Cognesy\Instructor\Schema\Data\Reference;
use Cognesy\Instructor\Schema\Utils\ReferenceQueue;

class ToolCallBuilder {
    private ReferenceQueue $references;
    private SchemaFactory $schemaFactory;

    public function __construct(
        SchemaFactory $schemaFactory,
        ReferenceQueue $referenceQueue,
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->references = $referenceQueue;
    }

    /**
     * Extract the schema model from a function and constructs a function call JSON schema array
     */
    public function renderToolCall(
        array $jsonSchema,
        string $name,
        string $description
    ) : array {
        $toolCall = [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
            ]
        ];
        if ($this->references->hasQueued()) {
            $toolCall['function']['parameters']['$defs'] = $this->definitions();
        }
        foreach ($jsonSchema as $key => $value) {
            $toolCall['function']['parameters'][$key] = $value;
        }
        return [$toolCall];
    }

    /**
     * Recursively extract the schema definitions from the references
     */
    protected function definitions() : array {
        $definitions = [];
        while($this->references->hasQueued()) {
            $reference = $this->references->dequeue();
            if ($reference == null) {
                break;
            }
            $definitions[$reference->classShort] = $this->schemaFactory
                ->schema($reference->class)
                ->toJsonSchema();
        }
        return array_reverse($definitions);
    }

    /**
     * Called when an object reference is found
     */
    public function onObjectRef(Reference $reference) {
        $this->references->queue($reference);
    }
}
