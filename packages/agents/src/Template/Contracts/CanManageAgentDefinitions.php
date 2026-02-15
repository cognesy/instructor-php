<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Contracts;

use Cognesy\Agents\Template\Data\AgentDefinition;

interface CanManageAgentDefinitions
{
    public function has(string $name): bool;

    public function get(string $name): AgentDefinition;

    /** @return array<string, AgentDefinition> */
    public function all(): array;

    /** @return array<int, string> */
    public function names(): array;

    public function count(): int;

    public function register(AgentDefinition $definition): void;

    public function registerMany(AgentDefinition ...$definitions): void;

    public function loadFromFile(string $path): void;

    public function loadFromDirectory(string $path, bool $recursive = false): void;

    public function autoDiscover(
        string $projectPath,
        ?string $packagePath = null,
        ?string $userPath = null,
    ): void;

    /** @return array<string, string> */
    public function errors(): array;
}
