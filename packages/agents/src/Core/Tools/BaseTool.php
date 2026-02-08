<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Tools;

use Cognesy\Agents\Core\Contracts\ToolInterface;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Utils\Result\Result;
use Throwable;

abstract class BaseTool implements ToolInterface, CanAccessAgentState
{
    protected string $name;
    protected string $description;
    protected ?array $cachedParamsJsonSchema = null;
    protected ?AgentState $agentState = null;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
    ) {
        $this->name = $name ?? static::class;
        $this->description = $description ?? '';
    }

    /**
     * Inject the current agent execution state.
     * Tools that need state access can use $this->agentState.
     */
    #[\Override]
    public function withAgentState(AgentState $state): static {
        $clone = clone $this;
        $clone->agentState = $state;
        return $clone;
    }

    /**
     * Subclasses must implement __invoke with their specific signature
     */
    abstract public function __invoke(mixed ...$args): mixed;

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function description(): string {
        return $this->description;
    }

    #[\Override]
    public function use(mixed ...$args): Result {
        try {
            $value = $this->__invoke(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return Result::success($value);
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->paramsJsonSchema(),
            ],
        ];
    }

    /**
     * Level 1: Metadata - minimal information for browsing/discovery
     * Override in subclasses to provide tags, capabilities, etc.
     */
    #[\Override]
    public function metadata(): array {
        $namespace = $this->extractNamespace($this->name());
        $summary = $this->extractSummary($this->description());

        $metadata = [
            'name' => $this->name(),
            'summary' => $summary,
        ];

        if ($namespace !== null) {
            $metadata['namespace'] = $namespace;
        }

        return $metadata;
    }

    /**
     * Level 2: Full specification - complete tool documentation
     * Override in subclasses to provide examples, errors, notes, etc.
     */
    #[\Override]
    public function instructions(): array {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => $this->paramsJsonSchema(),
            'returns' => 'mixed',
        ];
    }

    protected function arg(array $args, string $name, int $position, mixed $default = null): mixed {
        return $args[$name] ?? $args[$position] ?? $default;
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function paramsJsonSchema(): array {
        if (!isset($this->cachedParamsJsonSchema)) {
            $this->cachedParamsJsonSchema = StructureFactory::fromMethodName(static::class, '__invoke')
                ->toSchema()
                ->toJsonSchema();
        }
        return $this->cachedParamsJsonSchema;
    }

    /**
     * Extract namespace from tool name (e.g., "file.read" -> "file")
     */
    protected function extractNamespace(string $name): ?string {
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            array_pop($parts); // Remove action
            return implode('.', $parts);
        }
        return null;
    }

    /**
     * Extract summary from description (first sentence or first line)
     */
    protected function extractSummary(string $description): string {
        if ($description === '') {
            return '';
        }

        // Try to get first sentence (up to period)
        if (preg_match('/^([^.]+\.)/', $description, $matches)) {
            return trim($matches[1]);
        }

        // Try to get first line
        $lines = explode("\n", $description);
        $firstLine = trim($lines[0]);

        // Limit to reasonable length for summary
        if (strlen($firstLine) > 80) {
            return substr($firstLine, 0, 77) . '...';
        }

        return $firstLine;
    }
}
