<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Data\Reference;

class ObjectRefSchema extends Schema
{
    private string $defsLabel = '$defs';

    public function toArray(callable $refCallback = null) : array
    {
        $class = $this->className($this->type->class);
        $id = "#/{$this->defsLabel}/{$class}";
        if ($refCallback) {
            $refCallback(new Reference(
                id: $id,
                class: $this->type->class,
                classShort: $class
            ));
        }
        return array_filter([
            '$ref' => $id,
            'description' => $this->description,
            '$comment' => $this->type->class,
        ]);
    }

    private function className(string $fqcn) : string
    {
        $classSegments = explode('\\', $fqcn);
        return array_pop($classSegments);
    }
}