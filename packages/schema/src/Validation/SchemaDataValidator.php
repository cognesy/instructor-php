<?php declare(strict_types=1);

namespace Cognesy\Schema\Validation;

use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\TypeIdentifier;

final readonly class SchemaDataValidator
{
    public function __construct(
        private Schema $schema,
    ) {}

    public function validate(mixed $data): ValidationResult
    {
        $errors = [];
        $this->validateValue($this->schema, $data, '', $errors);

        return $errors === []
            ? ValidationResult::valid()
            : ValidationResult::invalid($errors, 'Schema data validation failed');
    }

    /** @param list<ValidationError> $errors */
    private function validateValue(Schema $schema, mixed $value, string $path, array &$errors): void
    {
        if ($value === null) {
            if (!$schema->isNullable()) {
                $errors[] = new ValidationError($this->displayPath($path), $value, 'Value cannot be null.');
            }
            return;
        }

        if (TypeInfo::isDateTimeClass($schema->type())) {
            if (!is_string($value) && !$value instanceof \DateTimeInterface) {
                $errors[] = new ValidationError($this->displayPath($path), $value, 'Expected date/time string.');
            }
            return;
        }

        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            $this->validateObject($schema, $value, $path, $errors);
            return;
        }

        if ($schema instanceof CollectionSchema) {
            $this->validateCollection($schema, $value, $path, $errors);
            return;
        }

        $allowed = $schema->enumValues ?? [];
        if ($allowed !== [] && !in_array($value, $allowed, true)) {
            $errors[] = new ValidationError($this->displayPath($path), $value, 'Value is not in enum/options list.');
            return;
        }

        $type = $schema->type();
        if (TypeInfo::isEnum($type)) {
            $allowed = TypeInfo::enumValues($type);
            if ($allowed !== [] && !in_array($value, $allowed, true)) {
                $errors[] = new ValidationError($this->displayPath($path), $value, 'Value is not in enum/options list.');
            }
            return;
        }

        $valid = match (true) {
            $type->isIdentifiedBy(TypeIdentifier::INT) => is_int($value),
            $type->isIdentifiedBy(TypeIdentifier::FLOAT) => is_float($value) || is_int($value),
            TypeInfo::isBool($type) => is_bool($value),
            $type->isIdentifiedBy(TypeIdentifier::STRING) => is_string($value),
            TypeInfo::isArray($type) => is_array($value),
            TypeInfo::isObject($type) => is_array($value) || is_object($value),
            default => true,
        };

        if (!$valid) {
            $errors[] = new ValidationError(
                $this->displayPath($path),
                $value,
                'Expected ' . TypeInfo::shortName($type) . '.',
            );
        }
    }

    /** @param list<ValidationError> $errors */
    private function validateObject(
        ObjectSchema|ArrayShapeSchema $schema,
        mixed $value,
        string $path,
        array &$errors,
    ): void {
        $record = is_object($value) ? get_object_vars($value) : $value;
        if (!is_array($record)) {
            $errors[] = new ValidationError($this->displayPath($path), $value, 'Expected object/associative array.');
            return;
        }

        foreach ($schema->required as $requiredField) {
            if (!array_key_exists($requiredField, $record)) {
                $errors[] = new ValidationError($this->path($path, $requiredField), null, 'Missing required field.');
            }
        }

        foreach ($schema->getPropertySchemas() as $name => $propertySchema) {
            if (array_key_exists($name, $record)) {
                $this->validateValue($propertySchema, $record[$name], $this->path($path, $name), $errors);
            }
        }
    }

    /** @param list<ValidationError> $errors */
    private function validateCollection(CollectionSchema $schema, mixed $value, string $path, array &$errors): void
    {
        if (!is_array($value)) {
            $errors[] = new ValidationError($this->displayPath($path), $value, 'Expected collection array.');
            return;
        }

        foreach ($value as $index => $item) {
            $this->validateValue($schema->nestedItemSchema, $item, $this->path($path, (string) $index), $errors);
        }
    }

    private function displayPath(string $path): string
    {
        return $path === '' ? 'root' : $path;
    }

    private function path(string $base, string $segment): string
    {
        return $base === '' ? $segment : $base . '.' . $segment;
    }
}
