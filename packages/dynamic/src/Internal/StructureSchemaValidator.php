<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Internal;

use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\TypeIdentifier;

final class StructureSchemaValidator
{
    public function __construct(
        private readonly Schema $schema,
    ) {}

    /** @param array<string,mixed> $data */
    public function validate(array $data) : ValidationResult {
        $errors = [];
        $this->validateSchemaData($this->schema, $data, '', $errors);

        return $errors === []
            ? ValidationResult::valid()
            : ValidationResult::invalid($errors, 'Structure validation failed');
    }

    /** @param array<ValidationError> $errors */
    private function validateSchemaData(Schema $schema, mixed $value, string $path, array &$errors) : void {
        if ($value === null) {
            if ($schema->isNullable()) {
                return;
            }

            $errors[] = new ValidationError($path === '' ? 'root' : $path, $value, 'Value cannot be null.');
            return;
        }

        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            if (is_object($value)) {
                $value = get_object_vars($value);
            }

            if (!is_array($value)) {
                $errors[] = new ValidationError($path === '' ? 'root' : $path, $value, 'Expected object/associative array.');
                return;
            }

            $required = $schema->required;
            foreach ($required as $requiredField) {
                if (array_key_exists($requiredField, $value)) {
                    continue;
                }
                $errors[] = new ValidationError(self::path($path, $requiredField), null, 'Missing required field.');
            }

            foreach ($schema->getPropertySchemas() as $propertyName => $propertySchema) {
                if (!array_key_exists($propertyName, $value)) {
                    continue;
                }
                $this->validateSchemaData($propertySchema, $value[$propertyName], self::path($path, $propertyName), $errors);
            }

            return;
        }

        if ($schema instanceof CollectionSchema) {
            if (!is_array($value)) {
                $errors[] = new ValidationError($path, $value, 'Expected collection array.');
                return;
            }

            foreach ($value as $index => $item) {
                $this->validateSchemaData($schema->nestedItemSchema, $item, self::path($path, (string) $index), $errors);
            }

            return;
        }

        $type = $schema->type();
        if (TypeInfo::isEnum($type)) {
            $allowed = $schema->enumValues ?? TypeInfo::enumValues($type);
            if ($allowed === [] || in_array($value, $allowed, true)) {
                return;
            }

            $errors[] = new ValidationError($path, $value, 'Value is not in enum/options list.');
            return;
        }

        if ($type->isIdentifiedBy(TypeIdentifier::INT) && !is_int($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected integer.');
            return;
        }

        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT) && !is_float($value) && !is_int($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected float.');
            return;
        }

        if (TypeInfo::isBool($type) && !is_bool($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected boolean.');
            return;
        }

        if ($type->isIdentifiedBy(TypeIdentifier::STRING) && !is_string($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected string.');
            return;
        }

        if (TypeInfo::isArray($type) && !is_array($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected array.');
            return;
        }

        if (TypeInfo::isObject($type) && !is_array($value) && !is_object($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected object-compatible value.');
        }
    }

    private static function path(string $base, string $segment) : string {
        if ($base === '') {
            return $segment;
        }

        return $base . '.' . $segment;
    }
}
