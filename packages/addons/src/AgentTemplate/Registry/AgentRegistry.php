<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Registry;

use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Exceptions\AgentNotFoundException;
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\SubagentProvider;
use Cognesy\Addons\AgentTemplate\Spec\AgentSpec;
use Cognesy\Addons\AgentTemplate\Spec\AgentSpecParser;
use InvalidArgumentException;

final class AgentRegistry implements SubagentProvider
{
    /** @var array<string, AgentSpec> */
    private array $specs = [];
    /** @var array<string, string> */
    private array $errors = [];

    private AgentSpecParser $parser;

    public function __construct(?AgentSpecParser $parser = null) {
        $this->parser = $parser ?? new AgentSpecParser();
    }

    // REGISTRATION /////////////////////////////////////////////////

    /**
     * @throws InvalidArgumentException if validation fails
     */
    public function register(AgentSpec $spec, ?Tools $parentTools = null): void {
        if ($parentTools !== null) {
            $errors = $spec->validate($parentTools);
            if (!empty($errors)) {
                throw new InvalidArgumentException(
                    "Invalid agent '{$spec->name}':\n" . implode("\n", $errors)
                );
            }
        }

        $this->specs[$spec->name] = $spec;
    }

    public function registerMultiple(?Tools $parentTools = null, AgentSpec ...$specs): void {
        foreach ($specs as $spec) {
            $this->register($spec, $parentTools);
        }
    }

    // RETRIEVAL ////////////////////////////////////////////////////

    /**
     * @throws AgentNotFoundException if agent not found
     */
    #[\Override]
    public function get(string $name): AgentSpec {
        if (!$this->has($name)) {
            throw new AgentNotFoundException(
                "Agent '{$name}' not found. Available: " . implode(', ', $this->names())
            );
        }
        return $this->specs[$name];
    }

    public function has(string $name): bool {
        return isset($this->specs[$name]);
    }

    /** @return array<string, AgentSpec> */
    #[\Override]
    public function all(): array {
        return $this->specs;
    }

    /** @return array<string> */
    #[\Override]
    public function names(): array {
        return array_keys($this->specs);
    }

    #[\Override]
    public function count(): int {
        return count($this->specs);
    }

    /** @return array<string, string> Map of name => description */
    public function descriptions(): array {
        $descriptions = [];
        foreach ($this->specs as $name => $spec) {
            $descriptions[$name] = $spec->description;
        }
        return $descriptions;
    }

    /** @return array<string, string> Accumulated errors keyed by file path */
    public function errors(): array {
        return $this->errors;
    }

    // FILE LOADING /////////////////////////////////////////////////

    public function loadFromFile(string $path, ?Tools $parentTools = null): AgentSpecLoadResult {
        return $this->applyResult($this->loadSingleFile($path, $parentTools));
    }

    public function loadFromDirectory(string $path, bool $recursive = false, ?Tools $parentTools = null): AgentSpecLoadResult {
        if (!is_dir($path)) {
            return new AgentSpecLoadResult();
        }

        $specs = [];
        $errors = [];

        $files = $recursive
            ? $this->findMarkdownFilesRecursively($path)
            : (glob($path . '/*.md') ?: []);

        foreach ($files as $file) {
            $result = $this->loadSingleFile($file, $parentTools);
            $specs = array_merge($specs, $result->specs);
            $errors = array_merge($errors, $result->errors);
        }

        return $this->applyResult(new AgentSpecLoadResult($specs, $errors));
    }

    public function loadFromJson(array $data, ?Tools $parentTools = null): void {
        $spec = $this->parser->parseJson($data);
        $this->register($spec, $parentTools);
    }

    // AUTO-DISCOVERY ///////////////////////////////////////////////

    public function autoDiscover(
        ?string $projectPath = null,
        ?string $packagePath = null,
        ?string $userPath = null,
        ?Tools $parentTools = null,
    ): AgentSpecLoadResult {
        $projectPath = $projectPath ?? (getcwd() ?: '/tmp');

        $specs = [];
        $errors = [];

        // Load in reverse priority order (lower priority first, so higher priority overwrites)
        $paths = [
            $userPath,
            $packagePath,
            $projectPath . '/.claude/agents',
        ];

        foreach ($paths as $path) {
            if ($path === null || !is_dir($path)) {
                continue;
            }
            $result = $this->loadFromDirectory($path, false, $parentTools);
            $specs = array_merge($specs, $result->specs);
            $errors = array_merge($errors, $result->errors);
        }

        return new AgentSpecLoadResult($specs, $errors);
    }

    // MANAGEMENT ///////////////////////////////////////////////////

    public function remove(string $name): void {
        unset($this->specs[$name]);
    }

    public function clear(): void {
        $this->specs = [];
    }

    // PRIVATE //////////////////////////////////////////////////////

    private function loadSingleFile(string $path, ?Tools $parentTools): AgentSpecLoadResult {
        try {
            $spec = $this->parser->parseMarkdownFile($path);
            $this->register($spec, $parentTools);
        } catch (\Throwable $e) {
            return new AgentSpecLoadResult(
                errors: [$path => $e->getMessage()],
            );
        }

        return new AgentSpecLoadResult(
            specs: [$spec->name => $spec],
        );
    }

    private function applyResult(AgentSpecLoadResult $result): AgentSpecLoadResult {
        $this->errors = array_merge($this->errors, $result->errors);
        return $result;
    }

    /** @return list<string> */
    private function findMarkdownFilesRecursively(string $path): array {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );
        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
