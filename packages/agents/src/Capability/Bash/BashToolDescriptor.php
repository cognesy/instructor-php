<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Bash;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class BashToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'bash',
            description: <<<'DESC'
Execute a bash command and return stdout/stderr. Use for shell operations, not file reading.

Examples:
- "git status" → check git state
- "composer install" → install dependencies
- "php artisan migrate" → run migrations
- "grep -r 'TODO' src/" → search file contents
- "npm run build" → run build scripts

Prefer dedicated tools when available: read_file over cat, search_files over find.
DESC,
            metadata: [
                'name' => 'bash',
                'summary' => 'Execute shell commands in a sandbox with output and safety limits.',
                'namespace' => 'system',
                'tags' => ['shell', 'command', 'sandbox'],
            ],
            instructions: [
                'parameters' => [
                    'command' => 'Bash command string to execute.',
                ],
                'returns' => 'Command stdout/stderr text with error or truncation markers when applicable.',
                'usage' => [
                    'Prefer dedicated tools when available (read_file, search_files, edit_file, write_file).',
                    'Use short, deterministic commands and avoid interactive shells.',
                ],
                'errors' => [
                    'Dangerous commands are blocked by policy.',
                    'Non-zero exit code is returned in tool output.',
                    'Execution timeout and output caps may truncate results.',
                ],
            ],
        );
    }
}
