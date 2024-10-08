<?php

namespace Cognesy\Instructor\Features\Schema\Utils;

use Cognesy\Instructor\Features\Schema\Data\Reference;

class ReferenceQueue
{
    /**
     * @var Reference[]
     */
    private array $references = [];

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
}
