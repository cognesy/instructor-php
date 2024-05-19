<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use BackedEnum;
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
                ($field->typeDetails()->type === 'enum') => $value->value,
                ($field->typeDetails()->type === 'array') => $this->serializeArrayField($value),
                ($field->typeDetails()->class !== null) => $this->serializeObjectField($value),
                default => $value,
            };
        }
        return $data;
    }

    private function serializeObjectField(object $object) : mixed {
        return match(true) {
            (method_exists($object, 'toArray')) => $object->toArray(),
            ($object instanceof Structure) => $object->toArray(),
            ($object instanceof \DateTime) => $object->format('Y-m-d H:i:s'),
            ($object instanceof \DateTimeImmutable) => $object->format('Y-m-d H:i:s'),
            (is_object($object) && ($object instanceof BackedEnum)) => $object->value,
            default => $this->deserializer->toArray($object),
        };
    }

    private function serializeArrayField(array $array) : array {
        return array_map(function($item) {
            return match(true) {
                is_array($item) => $this->serializeArrayField($item),
                is_object($item) => $this->serializeObjectField($item),
                default => $item,
            };
        }, $array);
    }
}