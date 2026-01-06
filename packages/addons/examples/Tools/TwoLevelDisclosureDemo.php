<?php

declare(strict_types=1);

namespace Cognesy\Addons\Examples\Tools;

use Cognesy\Addons\Agent\Tools\BaseTool;

/**
 * Demonstration of two-level progressive disclosure for tools
 *
 * Level 1 (metadata): Minimal information for browsing
 * - name, summary, namespace
 * - Target: 10-30 tokens
 *
 * Level 2 (fullSpec): Complete documentation
 * - name, description, parameters, usage, examples, errors, notes
 * - Target: 50-200 tokens
 */

// Example Tool: file.read
class FileReadTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'file.read',
            description: 'Reads a file from the filesystem. Supports reading specific line ranges for large files. Returns file contents as string.'
        );
    }

    public function __invoke(
        string $file_path,
        ?int $offset = null,
        ?int $limit = null
    ): string {
        // Implementation would go here
        return "File contents...";
    }

    /**
     * Override to provide additional metadata fields
     */
    #[\Override]
    public function metadata(): array
    {
        return [
            ...parent::metadata(),
            'tags' => ['file', 'io', 'read'],
            'constraints' => ['read-only'],
        ];
    }

    /**
     * Override to provide complete documentation
     */
    #[\Override]
    public function fullSpec(): array
    {
        return [
            ...parent::fullSpec(),
            'usage' => 'file.read(file_path="/path/to/file.txt")',
            'examples' => [
                [
                    'code' => 'file.read(file_path="/var/log/app.log")',
                    'description' => 'Read entire file',
                ],
                [
                    'code' => 'file.read(file_path="/var/log/app.log", offset=100, limit=50)',
                    'description' => 'Read lines 100-150',
                ],
            ],
            'errors' => [
                [
                    'type' => 'FileNotFoundException',
                    'when' => 'File does not exist at specified path',
                ],
                [
                    'type' => 'PermissionException',
                    'when' => 'File exists but is not readable',
                ],
            ],
            'notes' => [
                'For files > 10MB, consider using offset/limit to read in chunks',
                'Line endings are preserved as-is (\n or \r\n)',
                'Binary files return raw bytes as string',
            ],
        ];
    }
}

// Example Tool: task.code_review
class TaskCodeReviewTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'task.code_review',
            description: 'Reviews code for quality, bugs, and security issues. Performs comprehensive analysis including static analysis, security scanning, and best practices validation.'
        );
    }

    public function __invoke(
        string $target,
        ?array $focus_areas = null
    ): array {
        // Implementation would go here
        return ['issues' => []];
    }

    #[\Override]
    public function metadata(): array
    {
        return [
            ...parent::metadata(),
            'tags' => ['code-quality', 'review', 'security'],
            'capabilities' => ['code-analysis', 'bug-detection', 'security-analysis'],
            'constraints' => ['read-only'],
        ];
    }

    #[\Override]
    public function fullSpec(): array
    {
        return [
            ...parent::fullSpec(),
            'usage' => 'task.code_review(target="src/")',
            'returns' => 'array<string, mixed>',
            'examples' => [
                [
                    'code' => 'task.code_review(target="src/Authentication")',
                    'description' => 'Review authentication module',
                ],
                [
                    'code' => 'task.code_review(target="src/", focus_areas=["security"])',
                    'description' => 'Security-focused review of entire codebase',
                ],
            ],
            'notes' => [
                'Analysis may take 30-60 seconds for large codebases',
                'Returns structured report with severity levels',
                'Integrates with existing static analysis tools',
            ],
        ];
    }
}

// Demo usage
function demonstrateTwoLevelDisclosure(): void
{
    $fileReadTool = new FileReadTool();
    $codeReviewTool = new TaskCodeReviewTool();

    echo "=== LEVEL 1: METADATA (Browse/Discovery) ===\n\n";

    echo "File Read Tool:\n";
    print_r($fileReadTool->metadata());
    echo "\n";

    echo "Code Review Tool:\n";
    print_r($codeReviewTool->metadata());
    echo "\n";

    echo "=== LEVEL 2: FULL SPECIFICATION (Details) ===\n\n";

    echo "File Read Tool (Full Spec):\n";
    print_r($fileReadTool->fullSpec());
    echo "\n";

    echo "Code Review Tool (Full Spec):\n";
    print_r($codeReviewTool->fullSpec());
    echo "\n";

    echo "=== TOKEN COMPARISON ===\n\n";

    $metadataTokens = strlen(json_encode($fileReadTool->metadata())) / 4;
    $fullSpecTokens = strlen(json_encode($fileReadTool->fullSpec())) / 4;

    echo "Metadata: ~{$metadataTokens} tokens\n";
    echo "Full Spec: ~{$fullSpecTokens} tokens\n";
    echo "Reduction: " . round((1 - $metadataTokens / $fullSpecTokens) * 100, 1) . "%\n";
}

// Run demo if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    demonstrateTwoLevelDisclosure();
}
