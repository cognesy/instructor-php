<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Scalar;

use BackedEnum;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Exception;
use ReflectionEnum;

/**
 * Scalar value adapter.
 * Improved DX via simplified retrieval of scalar value from LLM response.
 */
final class Scalar implements CanProvideJsonSchema, CanDeserializeSelf, CanTransformSelf, CanValidateSelf
{
    public mixed $value = null;

    public string $name = 'value';
    public string $description = 'Response value';
    public ValueType $type = ValueType::STRING;
    public bool $required = true;
    public mixed $defaultValue = null;
    public array $options = [];
    /** @var class-string<BackedEnum>|null */
    public ?string $enumType = null;

    public function __construct(
        string $name = 'value',
        string $description = 'Response value',
        ValueType $type = ValueType::STRING,
        bool $required = true,
        mixed $defaultValue = null,
        ?string $enumType = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->required = $required;
        $this->defaultValue = $defaultValue;
        $this->type = $type;
        $this->initEnum($enumType, $type);
    }

    public static function integer(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::INTEGER,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    public static function float(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::FLOAT,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    public static function string(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::STRING,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    public static function boolean(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::BOOLEAN,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    /** @param class-string<BackedEnum> $enumType */
    public static function enum(
        string $enumType,
        string $name = 'enum',
        string $description = 'Select correct option',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        if (!self::isEnum($enumType)) {
            throw new Exception("Enum class does not exist or is not BackedEnum: {$enumType}");
        }

        return new self(
            name: $name,
            description: $description,
            required: $required,
            defaultValue: $defaultValue,
            enumType: $enumType,
        );
    }

    #[\Override]
    public function toJsonSchema() : array {
        $name = $this->name;
        $schema = [
            'type' => 'object',
            'properties' => [
                $name => [
                    'x-php-class' => $this->enumType ?? '',
                    'description' => $this->description,
                    'type' => $this->type->toJsonType(),
                ],
            ],
            'x-php-class' => self::class,
        ];

        if ($this->options !== []) {
            $schema['properties'][$name]['enum'] = $this->options;
        }

        if ($this->required) {
            $schema['required'] = [$name];
        }

        return $schema;
    }

    #[\Override]
    public function fromArray(array $data) : static {
        $this->value = $data[$this->name] ?? $this->defaultValue;
        return $this;
    }

    #[\Override]
    public function transform() : mixed {
        if (self::isEnum($this->enumType)) {
            assert($this->enumType !== null);
            return ($this->enumType)::from($this->value);
        }

        return match ($this->type) {
            ValueType::STRING => (string) $this->value,
            ValueType::INTEGER => (int) $this->value,
            ValueType::FLOAT => (float) $this->value,
            ValueType::BOOLEAN => (bool) $this->value,
            ValueType::ENUM => $this->value,
        };
    }

    #[\Override]
    public function validate() : ValidationResult {
        $errors = [];

        if ($this->required && $this->value === null) {
            $errors[] = new ValidationError(
                $this->name,
                $this->value,
                "Value '{$this->name}' is required",
            );
        }

        if ($this->options !== [] && !in_array($this->value, $this->options, true)) {
            $errors[] = new ValidationError(
                $this->name,
                $this->value,
                "Value '{$this->name}' must be one of: " . implode(', ', $this->options),
            );
        }

        return ValidationResult::make($errors, "Validation failed for '{$this->name}'");
    }

    private function initEnum(?string $enumType, ValueType $type) : void {
        if ($enumType === null || $enumType === '') {
            return;
        }

        $this->enumType = $enumType;
        $this->options = $this->getEnumValues($enumType);

        if ($this->options !== []) {
            $this->type = $this->getEnumValueType();
            return;
        }

        $this->type = $type;
    }

    private function getEnumValueType() : ValueType {
        if ($this->options === []) {
            throw new Exception('Enum options are not set');
        }

        return match (true) {
            is_string($this->options[0]) => ValueType::STRING,
            is_int($this->options[0]) => ValueType::INTEGER,
            default => throw new Exception('Enum type is not supported: ' . gettype($this->options[0])),
        };
    }

    /**
     * @return list<int|string>
     */
    private function getEnumValues(string $enumType) : array {
        if (!enum_exists($enumType)) {
            throw new Exception("Enum class does not exist: {$enumType}");
        }

        $cases = (new ReflectionEnum($enumType))->getCases();
        $values = [];

        foreach ($cases as $case) {
            $caseValue = $case->getValue();
            if ($caseValue instanceof BackedEnum) {
                $values[] = $caseValue->value;
            }
        }

        return $values;
    }

    /** @param class-string|null $enumType */
    private static function isEnum(?string $enumType) : bool {
        return $enumType !== null
            && $enumType !== ''
            && class_exists($enumType)
            && is_subclass_of($enumType, BackedEnum::class);
    }
}
