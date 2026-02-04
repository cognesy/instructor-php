<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\AgentDefinitionProvider;
use Cognesy\Agents\Exceptions\AgentNotFoundException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Stores agent definitions (data) keyed by name. Loads from .md/.yaml/.yml files. */
final class AgentDefinitionRegistry implements AgentDefinitionProvider
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

    // REGISTRATION /////////////////////////////////////////////////

    public function register(AgentDefinition $definition): void {
        $this->definitions[$definition->name] = $definition;
    }

    public function registerMany(AgentDefinition ...$definitions): void {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    // AGENT DEFINITION PROVIDER ////////////////////////////////////

    #[\Override]
    public function get(string $name): AgentDefinition {
        if (!$this->has($name)) {
            $available = implode(', ', $this->names());
            throw new AgentNotFoundException(
                "Agent '{$name}' not found. Available: {$available}"
            );
        }

        return $this->definitions[$name];
    }

    public function has(string $name): bool {
        return isset($this->definitions[$name]);
    }

    /** @return array<string, AgentDefinition> */
    #[\Override]
    public function all(): array {
        return $this->definitions;
    }

    /** @return array<int, string> */
    #[\Override]
    public function names(): array {
        return array_values(array_keys($this->definitions));
    }

    #[\Override]
    public function count(): int {
        return count($this->definitions);
    }

    // FILE LOADING /////////////////////////////////////////////////

    public function loadFromFile(string $path): void {
        try {
            $definition = $this->loader->loadFile($path);
            $this->register($definition);
        } catch (\Throwable $e) {
            $this->errors[$path] = $e->getMessage();
        }
    }

    public function loadFromDirectory(string $path, bool $recursive = false): void {
        if (!is_dir($path)) {
            return;
        }

        foreach ($this->listAgentFiles($path, $recursive) as $file) {
            $this->loadFromFile($file);
        }
    }

    public function autoDiscover(
        ?string $projectPath = null,
        ?string $packagePath = null,
        ?string $userPath = null,
    ): void {
        $projectPath = $projectPath ?? (getcwd() ?: '/tmp');

        $paths = [
            $userPath,
            $packagePath,
            $projectPath . '/.claude/agents',
        ];

        foreach ($paths as $path) {
            if ($path === null || !is_dir($path)) {
                continue;
            }
            $this->loadFromDirectory($path, false);
        }
    }

    /** @return array<string, string> */
    public function errors(): array {
        return $this->errors;
    }

    // PRIVATE //////////////////////////////////////////////////////

    /** @return list<string> */
    private function listAgentFiles(string $path, bool $recursive): array {
        $files = [];
        $extensions = ['md', 'yaml', 'yml'];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $extensions, true)) {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            $matches = glob($path . '/*.{md,yml,yaml}', GLOB_BRACE);
            if ($matches !== false) {
                $files = $matches;
            }
        }

        sort($files);

        return $files;
    }
}
