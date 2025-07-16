<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Scalar\Traits;

use Cognesy\Instructor\Extras\Scalar\ValueType;

trait HandlesTypeDefinitions
{
    use HandlesEnumType;

    /**
     * Create a new Scalar adapter for integer
     */
    static public function integer(
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

    /**
     * Create a new Scalar adapter for float
     */
    static public function float(
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

    /**
     * Create a new Scalar adapter for string
     */
    static public function string(
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

    /**
     * Create a new Scalar adapter for boolean
     */
    static public function boolean(
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

    /**
     * Create a new enum-like Scalar adapter (single choice from list of string options)
     */
    static public function enum(
        string $enumType,
        string $name = 'enum',
        string $description = 'Select correct option',
        bool   $required = true,
        mixed  $defaultValue = null,
    ) : self {
        if (!self::isEnum($enumType)) {
            throw new \Exception("Enum class does not exist or is not BackedEnum: {$enumType}");
        }
        return new self(
            name: $name,
            description: $description,
            required: $required,
            defaultValue: $defaultValue,
            enumType: $enumType,
        );
    }
}