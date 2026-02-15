<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Agents\Capability\StructuredOutput\SchemaDefinition;
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
final class SchemaRegistry implements CanManageSchemas
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
    #[\Override]
    public function register(string $name, string|SchemaDefinition $schema): void {
        $this->schemas[$name] = match (true) {
            is_string($schema) => SchemaDefinition::fromClass($schema),
            default => $schema,
        };
    }

    /**
     * Get a schema definition by name.
     *
     * @throws InvalidArgumentException if schema not found
     */
    #[\Override]
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
    #[\Override]
    public function has(string $name): bool {
        return isset($this->schemas[$name]);
    }

    /** @return array<string, SchemaDefinition> */
    #[\Override]
    public function all(): array {
        return $this->schemas;
    }

    /**
     * Get all schema names.
     *
     * @return string[]
     */
    #[\Override]
    public function names(): array {
        return array_keys($this->schemas);
    }

    /**
     * Get count of registered schemas.
     */
    #[\Override]
    public function count(): int {
        return count($this->schemas);
    }
}
