<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Internal;

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\TypeInfo;

final class StructureValueNormalizer
{
    public function __construct(
        private readonly Schema $schema,
    ) {}

    /** @param array<string,mixed> $values
     *  @return array<string,mixed>
     */
    public function normalizeRecord(array $values) : array {
        if (!$this->schema->hasProperties()) {
            return $values;
        }

        $normalized = [];
        foreach ($this->schema->getPropertySchemas() as $name => $propertySchema) {
            if (array_key_exists($name, $values)) {
                $normalized[$name] = $this->normalizeValue($propertySchema, $values[$name]);
                continue;
            }

            if ($propertySchema->hasDefaultValue()) {
                $normalized[$name] = $propertySchema->defaultValue();
            }
        }

        return $normalized;
    }

    public function normalizeFieldValue(string $field, mixed $value) : mixed {
        return $this->normalizeValue($this->schema->getPropertySchema($field), $value);
    }

    private function normalizeValue(Schema $schema, mixed $value) : mixed {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Structure) {
            return $value->toArray();
        }

        if ($schema instanceof CollectionSchema) {
            if (!is_array($value)) {
                return $value;
            }

            $normalized = [];
            foreach ($value as $item) {
                $normalized[] = $this->normalizeValue($schema->nestedItemSchema, $item);
            }
            return $normalized;
        }

        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            if (is_object($value)) {
                $className = TypeInfo::className($schema->type());
                if ($className !== null && $value instanceof $className) {
                    return $value;
                }
                $value = get_object_vars($value);
            }

            if (!is_array($value)) {
                return $value;
            }

            $normalized = [];
            foreach ($schema->getPropertySchemas() as $name => $propertySchema) {
                if (array_key_exists($name, $value)) {
                    $normalized[$name] = $this->normalizeValue($propertySchema, $value[$name]);
                    continue;
                }

                if ($propertySchema->hasDefaultValue()) {
                    $normalized[$name] = $propertySchema->defaultValue();
                }
            }

            if ($schema instanceof ObjectSchema) {
                $className = TypeInfo::className($schema->type());
                if ($className !== null && $className !== Structure::class && $className !== \stdClass::class && class_exists($className)) {
                    try {
                        return (new SymfonyDeserializer())->fromArray($normalized, $className);
                    } catch (\Throwable) {
                        return $normalized;
                    }
                }
            }

            return $normalized;
        }

        if (TypeInfo::isDateTimeClass($schema->type()) && $value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (TypeInfo::isEnum($schema->type()) && $value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        return $value;
    }
}
