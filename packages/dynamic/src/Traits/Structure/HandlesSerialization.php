<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\Structure;

use BackedEnum;
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\Data\TypeDetails;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

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
                ($field->typeDetails()->type === TypeDetails::PHP_ENUM) => $value->value,
                ($field->typeDetails()->type === TypeDetails::PHP_ARRAY) => $this->serializeArrayField($value),
                ($field->typeDetails()->type === TypeDetails::PHP_COLLECTION) => $this->serializeArrayField($value),
                ($field->typeDetails()->class == Structure::class) => $value?->toArray(),
                ($field->typeDetails()->class !== null) => $this->serializeObjectField($value),
                default => $value,
            };
        }
        return $data;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private function serializeObjectField(object $object) : mixed {
        // TODO: is there a way to consolidate value rendering?
        return match(true) {
            (method_exists($object, 'toArray')) => $object->toArray(),
            ($object instanceof Structure) => $object->toArray(),
            //($object instanceof CanDeserializeSelf) => $object->toArray(),
            ($object instanceof JsonSerializable) => $object->jsonSerialize(),
            ($object instanceof DateTimeInterface) => $object->format('Y-m-d H:i:s'),
            ($object instanceof DateTime) => $object->format('Y-m-d H:i:s'),
            ($object instanceof DateTimeImmutable) => $object->format('Y-m-d H:i:s'),
            ($object instanceof BackedEnum) => $object->value,
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
