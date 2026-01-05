<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\StructuredOutput;

use InvalidArgumentException;

/**
 * Registry of available schemas for structured output extraction.
 *
 * Schemas can be registered as:
 * - Simple class names: 'lead' => LeadForm::class
 * - Full definitions: 'lead' => new SchemaDefinition(...)
 *
 * Example:
 *   $registry = new SchemaRegistry([
 *       'lead' => LeadForm::class,
 *       'contact' => ContactForm::class,
 *   ]);
 *
 *   $registry->register('project', new SchemaDefinition(
 *       class: ProjectUpdate::class,
 *       prompt: 'Extract project status updates',
 *   ));
 */
final class SchemaRegistry
{
    /** @var array<string, SchemaDefinition> */
    private array $schemas = [];

    /**
     * @param array<string, class-string|SchemaDefinition> $schemas
     */
    public function __construct(array $schemas = []) {
        foreach ($schemas as $name => $schema) {
            $this->register($name, $schema);
        }
    }

    /**
     * Register a schema by name.
     *
     * @param string $name Unique identifier for this schema
     * @param class-string|SchemaDefinition $schema Class name or full definition
     */
    public function register(string $name, string|SchemaDefinition $schema): self {
        $this->schemas[$name] = match (true) {
            is_string($schema) => SchemaDefinition::fromClass($schema),
            default => $schema,
        };
        return $this;
    }

    /**
     * Get a schema definition by name.
     *
     * @throws InvalidArgumentException if schema not found
     */
    public function get(string $name): SchemaDefinition {
        if (!$this->has($name)) {
            $available = implode(', ', array_keys($this->schemas));
            throw new InvalidArgumentException(
                "Schema '{$name}' not found. Available schemas: {$available}"
            );
        }
        return $this->schemas[$name];
    }

    /**
     * Check if a schema is registered.
     */
    public function has(string $name): bool {
        return isset($this->schemas[$name]);
    }

    /**
     * List all registered schema names with descriptions.
     *
     * @return array<string, string|null> Map of name => description
     */
    public function list(): array {
        $list = [];
        foreach ($this->schemas as $name => $definition) {
            $list[$name] = $definition->description;
        }
        return $list;
    }

    /**
     * Get all schema names.
     *
     * @return string[]
     */
    public function names(): array {
        return array_keys($this->schemas);
    }

    /**
     * Get count of registered schemas.
     */
    public function count(): int {
        return count($this->schemas);
    }
}
