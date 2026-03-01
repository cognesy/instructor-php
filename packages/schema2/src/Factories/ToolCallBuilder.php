<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Visitors\SchemaToJsonSchema;

class ToolCallBuilder {
    /** @var array<string, bool> */
    private array $referenceState = [];

    public function __construct(
        private readonly SchemaFactory $schemaFactory,
    ) {}

    /**
     * Extract the schema model from a function and constructs a function call JSON schema array
     */
    public function renderToolCall(
        array $jsonSchema,
        string $name,
        string $description,
    ) : array {
        $toolCall = [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
            ]
        ];
        if ($this->hasQueued()) {
            $toolCall['function']['parameters']['$defs'] = $this->definitions();
        }
        foreach ($jsonSchema as $key => $value) {
            $toolCall['function']['parameters'][$key] = $value;
        }
        return [$toolCall];
    }

    /**
     * Called when an object reference is found
     */
    public function onObjectRef(string $className) : void {
        if (!isset($this->referenceState[$className])) {
            $this->referenceState[$className] = false;
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions() : array {
        $definitions = [];
        while ($this->hasQueued()) {
            $className = $this->dequeue();
            if ($className === null) {
                break;
            }

            $classKey = $this->classKey($className);
            $schema = $this->schemaFactory->schema($className);
            $definitions[$classKey] = (new SchemaToJsonSchema())->toArray($schema, $this->onObjectRef(...));
        }

        return array_reverse($definitions);
    }

    private function hasQueued() : bool {
        foreach ($this->referenceState as $rendered) {
            if ($rendered === false) {
                return true;
            }
        }

        return false;
    }

    private function dequeue() : ?string {
        foreach ($this->referenceState as $className => $rendered) {
            if ($rendered === false) {
                $this->referenceState[$className] = true;
                return $className;
            }
        }

        return null;
    }

    private function classKey(string $className) : string {
        return str_replace('\\', '.', ltrim($className, '\\'));
    }
}
