<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Data;

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
        public string $type,
        private ?string $class = null,
        private ?object $instance = null,
    ) {}

    /**
     * Return data as raw associative array (skip deserialization).
     */
    public static function array(): self
    {
        return new self('array');
    }

    /**
     * Hydrate data into the specified class.
     *
     * @param class-string $class Target class for deserialization
     */
    public static function instanceOf(string $class): self
    {
        return new self('class', $class);
    }

    /**
     * Use a self-deserializing object instance.
     *
     * @param object $instance Object implementing CanDeserializeSelf
     */
    public static function selfDeserializing(object $instance): self
    {
        return new self('object', get_class($instance), $instance);
    }

    public function isArray(): bool
    {
        return $this->type === 'array';
    }

    public function isClass(): bool
    {
        return $this->type === 'class';
    }

    public function isObject(): bool
    {
        return $this->type === 'object';
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
