<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Utils\Json;
use DateTime;
use DateTimeImmutable;
use Exception;

trait HandlesDeserialization
{
    private Deserializer $deserializer;
    public bool $ignoreUnknownFields = false;

    public function fromArray(array $data): static {
        foreach ($data as $name => $fieldData) {
            if (!$this->has($name) && $this->ignoreUnknownFields) {
                continue;
            }
            $field = $this->field($name);
            if (empty($fieldData)) {
                if ($field->isRequired()) {
                    throw new \Exception("Required field `$name` of structure `$this->name` is empty.");
                }
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
            ($type->type === TypeDetails::PHP_ENUM) => ($type->class)::from($fieldData),
            ($type->type === TypeDetails::PHP_ARRAY) => $this->deserializeArray($field, $fieldData),
            ($type->class === null) => $fieldData,
            ($type->class === Structure::class) => $structure->get($name)->fromArray($fieldData),
            ($type->class === DateTime::class) => new DateTime($fieldData),
            ($type->class === DateTimeImmutable::class) => new DateTimeImmutable($fieldData),
            default => $this->deserializer->fromArray($fieldData, $type->class),
        };
        return $value;
    }

    private function deserializeArray(Field $field, mixed $fieldData) : mixed {
        $values = [];
        $typeDetails = $field->nestedTypeDetails();
        foreach($fieldData as $itemData) {
            $values[] = match(true) {
                ($typeDetails->type === TypeDetails::PHP_ENUM) => ($typeDetails->class)::from($itemData),
                ($typeDetails->type === TypeDetails::PHP_ARRAY) => throw new Exception('Nested arrays are not supported.'),
                ($typeDetails->class === null) => $itemData,
                ($typeDetails->class === Structure::class) => $field->value->fromArray($itemData),
                ($typeDetails->class === DateTime::class) => new DateTime($itemData),
                ($typeDetails->class === DateTimeImmutable::class) => new DateTimeImmutable($itemData),
                default => $this->deserializer->fromArray($itemData, $typeDetails->class),
            };
        }
        return $values;
    }
}