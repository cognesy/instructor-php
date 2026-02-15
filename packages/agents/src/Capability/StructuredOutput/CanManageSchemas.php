<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Agents\Capability\StructuredOutput\SchemaDefinition;

interface CanManageSchemas
{
    /**
     * @param class-string|SchemaDefinition $schema
     */
    public function register(string $name, string|SchemaDefinition $schema): void;

    public function has(string $name): bool;

    public function get(string $name): SchemaDefinition;

    /** @return array<string, SchemaDefinition> */
    public function all(): array;

    /** @return array<int, string> */
    public function names(): array;

    public function count(): int;
}
