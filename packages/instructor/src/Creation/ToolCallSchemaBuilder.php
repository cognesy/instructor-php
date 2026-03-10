<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\SchemaFactory;

final class ToolCallSchemaBuilder
{
    /** @var array<string, bool> */
    private array $referenceState = [];

    public function __construct(
        private readonly SchemaFactory $schemaFactory,
    ) {}

    /**
     * @param array<string, mixed> $jsonSchema
     */
    public function renderToolDefinitions(
        array $jsonSchema,
        string $name,
        string $description,
    ) : ToolDefinitions {
        $parameters = [];
        if ($this->hasQueued()) {
            $parameters['$defs'] = $this->definitions();
        }
        foreach ($jsonSchema as $key => $value) {
            $parameters[$key] = $value;
        }
        return new ToolDefinitions(
            new ToolDefinition($name, $description, $parameters),
        );
    }

    public function onObjectRef(string $className) : void {
        if (!isset($this->referenceState[$className])) {
            $this->referenceState[$className] = false;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions() : array {
        $definitions = [];
        while ($this->hasQueued()) {
            $className = $this->dequeue();
            if ($className === null) {
                break;
            }

            $classKey = $this->classKey($className);
            $schema = $this->schemaFactory->schema($className);
            $definitions[$classKey] = $this->schemaFactory->toJsonSchema($schema, $this->onObjectRef(...));
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

