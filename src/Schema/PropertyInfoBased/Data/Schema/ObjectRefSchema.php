<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema;

class ObjectRefSchema extends Schema
{
    private string $defsLabel = 'definitions';

    public function toArray(callable $refCallback = null) : array
    {
        $class = $this->className($this->type->class);
        $id = "#/{$this->defsLabel}/{$class}";
        if ($refCallback) {
            $refCallback(new Reference($id, $this->type->class));
        }
        return array_filter([
            '$ref' => $id,
            'description' => $this->description,
        ]);
    }

    private function className(string $fqcn) : string
    {
        $classSegments = explode('\\', $fqcn);
        return array_pop($classSegments);
    }
}