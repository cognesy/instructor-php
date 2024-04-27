<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Utils\Json;

trait HandlesDeserialization
{
    private Deserializer $deserializer;

    public function fromArray(array $data): static {
        foreach ($data as $name => $fieldData) {
            $field = $this->field($name);
            if (empty($fieldData)) {
//                if ($field->isRequired()) {
//                    throw new \Exception("Required field `$name` of structure `$this->name` is empty.");
//                }
                continue;
            }
            $value = $this->deserializeField($this, $field, $name, $fieldData);
            $this->set($name, $value);
        }
        return $this;
    }

    public function fromJson(string $jsonData): static {
        $data = Json::parse($jsonData);
        return $this->fromArray($data);
    }

    private function deserializeField(Structure $structure, Field $field, string $name, mixed $fieldData) : mixed {
        $type = $field->typeDetails();
        $value = match(true) {
            ($type === null) => throw new \Exception("Undefined field `$name` found in JSON data."),
            ($type->type === 'enum') => $fieldData,
            ($type->type === 'array') => $this->deserializeArray($structure->get($name), $field, $fieldData),
            ($type->class === null) => $fieldData,
            ($type->class === Structure::class) => $structure->get($name)->fromArray($fieldData),
            default => $this->deserializer->fromArray($fieldData, $type->class),
        };
        return $value;
    }

    private function deserializeArray(Structure $structure, Field $field, mixed $fieldData) : mixed {
return $fieldData;
        $values = [];
        foreach($fieldData as $index => $itemData) {
            $values[] = $this->deserializeField($structure, $field, $index, $itemData);
        }
        return $values;
    }
}