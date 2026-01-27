<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use Cognesy\Agents\Core\Exceptions\AgentNotFoundException;

final class AgentDefinitionRegistry
{
    /** @var array<string, AgentDefinition> */
    private array $definitions = [];
    /** @var array<string, string> */
    private array $errors = [];
    private AgentDefinitionLoader $loader;

    public function __construct(?AgentDefinitionLoader $loader = null)
    {
        $this->loader = $loader ?? new AgentDefinitionLoader();
    }

    public function register(AgentDefinition $definition): void
    {
        $this->definitions[$definition->id] = $definition;
    }

    public function registerMany(AgentDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function get(string $id): AgentDefinition
    {
        if (!$this->has($id)) {
            throw new AgentNotFoundException("Agent definition '{$id}' not found.");
        }

        return $this->definitions[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    public function loadFromFile(string $path): AgentDefinitionLoadResult
    {
        return $this->applyResult($this->loader->loadFromFile($path));
    }

    public function loadFromDirectory(string $path, bool $recursive = false): AgentDefinitionLoadResult
    {
        return $this->applyResult($this->loader->loadFromDirectory($path, $recursive));
    }

    /**
     * @param array<int, string> $paths
     */
    public function loadFromPaths(array $paths, bool $recursive = false): AgentDefinitionLoadResult
    {
        return $this->applyResult($this->loader->loadFromPaths($paths, $recursive));
    }

    public function autoDiscover(
        ?string $projectPath = null,
        ?string $packagePath = null,
        ?string $userPath = null,
    ): AgentDefinitionLoadResult {
        $projectPath = $projectPath ?? (getcwd() ?: '/tmp');

        $definitions = [];
        $errors = [];

        $paths = [
            $userPath,
            $packagePath,
            $projectPath . '/.claude/agents',
        ];

        foreach ($paths as $path) {
            if ($path === null || !is_dir($path)) {
                continue;
            }

            $result = $this->loadFromDirectory($path, false);
            $definitions = array_merge($definitions, $result->definitions);
            $errors = array_merge($errors, $result->errors);
        }

        return new AgentDefinitionLoadResult($definitions, $errors);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function applyResult(AgentDefinitionLoadResult $result): AgentDefinitionLoadResult
    {
        foreach ($result->definitions as $id => $definition) {
            $this->definitions[$id] = $definition;
        }

        $this->errors = array_merge($this->errors, $result->errors);

        return $result;
    }
}
