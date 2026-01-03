<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\Structure;

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use DateTime;
use DateTimeImmutable;
use Exception;

trait HandlesDeserialization
{
    private SymfonyDeserializer $deserializer;
    protected bool $ignoreUnknownFields = true;

    #[\Override]
    public function fromArray(array $data, ?string $toolName = null): static {
        foreach ($data as $name => $fieldData) {
            if ($this->ignoreUnknownFields && !$this->has($name)) {
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

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private function deserializeField(Structure $structure, Field $field, string $name, mixed $fieldData) : mixed {
        $type = $field->typeDetails();
        $value = match(true) {
            ($type->isEnum() && $type->class !== null) => ($type->class)::from($fieldData),
            ($type->isCollection()) => $this->deserializeCollection($field, $fieldData),
            ($type->isArray()) => is_array($fieldData) ? $fieldData : [$fieldData],
            ($type->class() === null) => $fieldData,
            ($type->class() === Structure::class) => $structure->get($name)->fromArray($fieldData),
            ($type->class() === DateTime::class) => new DateTime($fieldData),
            ($type->class() === DateTimeImmutable::class) => new DateTimeImmutable($fieldData),
            default => $this->deserializeObject($fieldData, $type->class()),
        };
        return $value;
    }

    /**
     * @param string|null $className
     */
    private function deserializeObject(mixed $data, ?string $className): mixed {
        if ($className === null) {
            throw new Exception('Class type required for deserialization');
        }
        /** @var class-string<object> $className */
        return $this->deserializer->fromArray($data, $className);
    }

    private function deserializeCollection(Field $field, mixed $fieldData) : mixed {
        $values = [];
        $typeDetails = $field->nestedType();
        foreach($fieldData as $itemData) {
            $values[] = match(true) {
                ($typeDetails->isScalar()) => $itemData,
                ($typeDetails->isEnum() && $typeDetails->class !== null) => ($typeDetails->class)::from($itemData),
                ($typeDetails->isCollection()) => throw new Exception('Nested collections are not supported.'),
                ($typeDetails->class() === Structure::class) && ($field->hasPrototype()) => $field->prototype()?->clone()->fromArray($itemData),
                ($typeDetails->class() === DateTime::class) => new DateTime($itemData),
                ($typeDetails->class() === DateTimeImmutable::class) => new DateTimeImmutable($itemData),
                ($typeDetails->isArray()) => is_array($itemData) ? $itemData : [$itemData],
                default => $this->deserializeObject($itemData, $typeDetails->class()),
            };
        }
        return $values;
    }
}