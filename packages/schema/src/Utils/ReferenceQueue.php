<?php

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Data\Reference;
use Cognesy\Schema\Factories\SchemaFactory;

class ReferenceQueue
{
    /**
     * @var \Cognesy\Schema\Data\Reference[]
     */
    private array $references = [];

    public function __construct(
        private readonly SchemaFactory $schemaFactory
    ) {}

    public function queue(Reference $reference) : void {
        if (!isset($this->references[$reference->class])) {
            $this->references[$reference->class] = $reference;
        }
    }

    public function dequeue() : ?Reference {
        foreach ($this->references as $class => $reference) {
            if ($reference->isRendered === false) {
                $this->references[$class]->isRendered = true;
                return $reference;
            }
        }
        return null;
    }

    public function hasQueued() : bool {
        foreach ($this->references as $reference) {
            if ($reference->isRendered === false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recursively extract the schema definitions from the references
     */
    public function definitions() : array {
        $definitions = [];
        while($this->hasQueued()) {
            $reference = $this->dequeue();
            if ($reference == null) {
                break;
            }
            $definitions[$reference->classShort] = $this->schemaFactory
                ->schema($reference->class)
                ->toJsonSchema();
        }
        return array_reverse($definitions);
    }
}
