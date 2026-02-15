<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Tools;

use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Traits\HasReflectiveSchema;

abstract class BaseTool extends StateAwareTool
{
    use HasReflectiveSchema;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
    ) {
        parent::__construct(new ToolDescriptor(
            name: $name ?? static::class,
            description: $description ?? '',
        ));
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
