<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Subagents;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Exceptions\SubagentNotFoundException;
use InvalidArgumentException;

final class SubagentRegistry
{
    /** @var array<string, AgentSpec> */
    private array $specs = [];

    private SubagentSpecParser $parser;

    public function __construct(?SubagentSpecParser $parser = null) {
        $this->parser = $parser ?? new SubagentSpecParser();
    }

    // REGISTRATION /////////////////////////////////////////////////

    /**
     * Register a subagent spec.
     *
     * @param AgentSpec $spec Subagent specification
     * @param Tools|null $parentTools Optional parent tools for validation
     * @throws InvalidArgumentException if validation fails
     */
    public function register(AgentSpec $spec, ?Tools $parentTools = null): void {
        // Validate tools if parent tools provided
        if ($parentTools !== null) {
            $errors = $spec->validate($parentTools);
            if (!empty($errors)) {
                throw new InvalidArgumentException(
                    "Invalid subagent '{$spec->name}':\n" . implode("\n", $errors)
                );
            }
        }

        $this->specs[$spec->name] = $spec;
    }

    /**
     * Register multiple subagent specs.
     *
     * @param AgentSpec ...$specs
     * @param Tools|null $parentTools Optional parent tools for validation
     */
    public function registerMultiple(?Tools $parentTools = null, AgentSpec ...$specs): void {
        foreach ($specs as $spec) {
            $this->register($spec, $parentTools);
        }
    }

    // RETRIEVAL ////////////////////////////////////////////////////

    /**
     * Get a subagent spec by name.
     *
     * @throws SubagentNotFoundException if subagent not found
     */
    public function get(string $name): AgentSpec {
        if (!$this->has($name)) {
            throw new SubagentNotFoundException(
                "Subagent '{$name}' not found. Available: " . implode(', ', $this->names())
            );
        }
        return $this->specs[$name];
    }

    /**
     * Check if subagent exists.
     */
    public function has(string $name): bool {
        return isset($this->specs[$name]);
    }

    /**
     * Get all registered subagent specs.
     *
     * @return array<string, AgentSpec>
     */
    public function all(): array {
        return $this->specs;
    }

    /**
     * Get all subagent names.
     *
     * @return array<string>
     */
    public function names(): array {
        return array_keys($this->specs);
    }

    /**
     * Get subagent descriptions for tool schema.
     *
     * @return array<string, string> Map of name => description
     */
    public function descriptions(): array {
        $descriptions = [];
        foreach ($this->specs as $name => $spec) {
            $descriptions[$name] = $spec->description;
        }
        return $descriptions;
    }

    // FILE LOADING /////////////////////////////////////////////////

    /**
     * Load a subagent spec from a markdown file.
     *
     * @param string $path Absolute path to .md file
     * @param Tools|null $parentTools Optional parent tools for validation
     */
    public function loadFromFile(string $path, ?Tools $parentTools = null): void {
        $spec = $this->parser->parseMarkdownFile($path);
        $this->register($spec, $parentTools);
    }

    /**
     * Load all subagent specs from a directory.
     *
     * @param string $path Directory path
     * @param bool $recursive Search subdirectories
     * @param Tools|null $parentTools Optional parent tools for validation
     */
    public function loadFromDirectory(string $path, bool $recursive = false, ?Tools $parentTools = null): void {
        if (!is_dir($path)) {
            return; // Silently skip missing directories
        }

        $pattern = $recursive ? '**/*.md' : '*.md';
        $files = glob($path . '/' . $pattern, GLOB_BRACE);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                try {
                    $this->loadFromFile($file, $parentTools);
                } catch (\Throwable $e) {
                    // Log error but continue loading other files
                    trigger_error(
                        "Failed to load subagent from {$file}: {$e->getMessage()}",
                        E_USER_WARNING
                    );
                }
            }
        }
    }

    /**
     * Load a subagent spec from JSON data.
     *
     * @param array<string, mixed> $data JSON-decoded data
     * @param Tools|null $parentTools Optional parent tools for validation
     */
    public function loadFromJson(array $data, ?Tools $parentTools = null): void {
        $spec = $this->parser->parseJson($data);
        $this->register($spec, $parentTools);
    }

    // AUTO-DISCOVERY ///////////////////////////////////////////////

    /**
     * Auto-discover and load subagent specs from standard locations.
     *
     * Priority (highest to lowest):
     * 1. Project-level (.claude/agents/)
     * 2. Package-level (vendor/cognesy/instructor-php/subagents/)
     * 3. User-level (~/.instructor-php/subagents/)
     *
     * Higher priority specs override lower priority specs with same name.
     *
     * @param string|null $projectPath Project directory (defaults to cwd)
     * @param string|null $packagePath Package subagents directory
     * @param string|null $userPath User subagents directory
     * @param Tools|null $parentTools Optional parent tools for validation
     */
    public function autoDiscover(
        ?string $projectPath = null,
        ?string $packagePath = null,
        ?string $userPath = null,
        ?Tools $parentTools = null,
    ): void {
        $projectPath = $projectPath ?? (getcwd() ?: '/tmp');

        // Load in reverse priority order (lower priority first, so higher priority overwrites)

        // Priority 3: User-level
        if ($userPath !== null) {
            $this->loadFromDirectory($userPath, false, $parentTools);
        }

        // Priority 2: Package-level
        if ($packagePath !== null) {
            $this->loadFromDirectory($packagePath, false, $parentTools);
        }

        // Priority 1: Project-level (highest priority)
        $this->loadFromDirectory($projectPath . '/.claude/agents', false, $parentTools);
    }

    // MANAGEMENT ///////////////////////////////////////////////////

    /**
     * Remove a subagent spec.
     */
    public function remove(string $name): void {
        unset($this->specs[$name]);
    }

    /**
     * Clear all registered specs.
     */
    public function clear(): void {
        $this->specs = [];
    }

    /**
     * Get count of registered subagents.
     */
    public function count(): int {
        return count($this->specs);
    }
}
