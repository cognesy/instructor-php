<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\Structure;

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Stringable;

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
            if ($fieldData === null) {
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
            ($type->class() === Structure::class) => $this->deserializeStructureField($structure, $name, $fieldData),
            ($type->class() === DateTime::class) => new DateTime($this->normalizeDateTimeInput($fieldData)),
            ($type->class() === DateTimeImmutable::class) => new DateTimeImmutable($this->normalizeDateTimeInput($fieldData)),
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
        if (!is_array($data)) {
            throw new Exception(sprintf(
                'Object field `%s` expects array input, got `%s`.',
                $className,
                get_debug_type($data),
            ));
        }
        /** @var class-string<object> $className */
        return $this->deserializer->fromArray($data, $className);
    }

    private function deserializeCollection(Field $field, mixed $fieldData) : mixed {
        if (!is_iterable($fieldData)) {
            throw new Exception(sprintf(
                'Collection field `%s` expects iterable input, got `%s`.',
                $field->name(),
                get_debug_type($fieldData),
            ));
        }

        $values = [];
        $typeDetails = $field->nestedType();
        foreach($fieldData as $itemData) {
            $values[] = match(true) {
                ($typeDetails->isScalar()) => $itemData,
                ($typeDetails->isEnum() && $typeDetails->class !== null) => ($typeDetails->class)::from($itemData),
                ($typeDetails->isCollection()) => throw new Exception('Nested collections are not supported.'),
                ($typeDetails->class() === Structure::class) && ($field->hasPrototype()) => $this->deserializeStructureCollectionItem($field, $itemData),
                ($typeDetails->class() === DateTime::class) => new DateTime($this->normalizeDateTimeInput($itemData)),
                ($typeDetails->class() === DateTimeImmutable::class) => new DateTimeImmutable($this->normalizeDateTimeInput($itemData)),
                ($typeDetails->isArray()) => is_array($itemData) ? $itemData : [$itemData],
                default => $this->deserializeObject($itemData, $typeDetails->class()),
            };
        }
        return $values;
    }

    private function deserializeStructureField(Structure $structure, string $name, mixed $fieldData): Structure {
        $nestedStructure = $structure->get($name);
        if (!$nestedStructure instanceof Structure) {
            throw new Exception(sprintf(
                'Structure field `%s` expected `%s`, got `%s`.',
                $name,
                Structure::class,
                get_debug_type($nestedStructure),
            ));
        }
        if (!is_array($fieldData)) {
            throw new Exception(sprintf(
                'Structure field `%s` expects array input, got `%s`.',
                $name,
                get_debug_type($fieldData),
            ));
        }
        return $nestedStructure->fromArray($fieldData);
    }

    private function deserializeStructureCollectionItem(Field $field, mixed $itemData): Structure {
        $prototype = $field->prototype();
        if ($prototype === null) {
            throw new Exception(sprintf(
                'Collection field `%s` does not define a structure prototype.',
                $field->name(),
            ));
        }
        if (!is_array($itemData)) {
            throw new Exception(sprintf(
                'Collection field `%s` expects structure array items, got `%s`.',
                $field->name(),
                get_debug_type($itemData),
            ));
        }
        return $prototype->clone()->fromArray($itemData);
    }

    private function normalizeDateTimeInput(mixed $input): string {
        return match(true) {
            $input instanceof DateTimeInterface => $input->format(DateTimeInterface::ATOM),
            is_scalar($input) => (string) $input,
            $input instanceof Stringable => (string) $input,
            default => throw new Exception(sprintf(
                'DateTime field expects scalar or stringable input, got `%s`.',
                get_debug_type($input),
            )),
        };
    }
}
