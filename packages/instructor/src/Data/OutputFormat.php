<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Enums\OutputFormatType;
use InvalidArgumentException;

/**
 * Value object representing the desired output format for deserialization.
 *
 * Separates schema specification (what structure LLM should produce)
 * from output format (how we want to receive the data).
 *
 * Three formats supported:
 * - array: Return raw associative array (no deserialization)
 * - class: Hydrate to a specific class (may differ from schema class)
 * - object: Use self-deserializing object (CanDeserializeSelf)
 */
final readonly class OutputFormat
{
    private function __construct(
        public OutputFormatType $type,
        private ?string $class = null,
        private ?object $instance = null,
    ) {}

    /**
     * Return data as raw associative array (skip deserialization).
     */
    public static function array(): self
    {
        return new self(OutputFormatType::AsArray);
    }

    /**
     * Hydrate data into the specified class.
     *
     * @param class-string $class Target class for deserialization
     */
    public static function instanceOf(string $class): self
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Output class does not exist: {$class}");
        }
        return new self(OutputFormatType::AsClass, $class);
    }

    public static function stdClass(): self
    {
        return self::instanceOf(\stdClass::class);
    }

    /**
     * Use a self-deserializing object instance.
     *
     * @param CanDeserializeSelf $instance Self-deserializing target instance
     */
    public static function selfDeserializing(CanDeserializeSelf $instance): self
    {
        return new self(OutputFormatType::AsObject, get_class($instance), $instance);
    }

    public function isArray(): bool
    {
        return $this->type === OutputFormatType::AsArray;
    }

    public function isClass(): bool
    {
        return $this->type === OutputFormatType::AsClass;
    }

    public function isObject(): bool
    {
        return $this->type === OutputFormatType::AsObject;
    }

    /**
     * @return class-string|null
     */
    public function targetClass(): ?string
    {
        /** @var class-string|null */
        return $this->class;
    }

    public function targetInstance(): ?object
    {
        return $this->instance;
    }
}
