<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Structure;

trait HandlesSerialization
{
    public function toArray() : array {
        $data = [];
        foreach ($this->fields as $fieldName => $field) {
            $value = $field->get();
            // if field is empty, skip it
            if ($field->isEmpty()) {
                if ($field->isRequired()) {
                    $data[$fieldName] = $value;
                }
                continue;
            }
            $data[$fieldName] = match(true) {
                ($field->typeDetails()->class == Structure::class) => $value?->toArray(),
                ($field->typeDetails()->type === 'enum') => $value,
                ($field->typeDetails()->class !== null) => $this->serializeObjectField($value),
                default => $value,
            };
        }
        return $data;
    }

    private function serializeObjectField(object $object) : mixed {
        // check if $object has a method named `toArray`
        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }
        // try to serialize the object using the `json_encode` function
        return $this->deserializer->toArray($object);
    }
}