<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\Bash;

use Cognesy\Agents\Tool\Contracts\CanContributeToPrompt;
use Cognesy\Agents\Tool\ToolDescriptor;
use Override;

final readonly class BashToolDescriptor extends ToolDescriptor implements CanContributeToPrompt
{
    public function __construct() {
        parent::__construct(
            name: 'bash',
            description: <<<'DESC'
Execute a bash command and return bounded stdout/stderr. Use for shell operations, not file reading.

Examples:
- "git status" → check git state
- "composer install" → install dependencies
- "php artisan migrate" → run migrations
- "grep -r 'TODO' src/" → search file contents
- "npm run build" → run build scripts

Prefer dedicated file tools over cat, sed-based rewrites, and shell redirection.
If output is truncated, narrow the command or redirect it to a file and inspect it with the dedicated read tool.
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
                'returns' => 'Bounded stdout/stderr with explicit exit, timeout, and recoverable truncation details.',
                'usage' => [
                    'Prefer dedicated file tools when reading, editing, or creating files.',
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

    #[Override]
    public function promptSnippet(): ?string {
        return 'Execute bash commands';
    }

    #[Override]
    public function promptGuidelines(): array {
        return [
            'Do not use cat or bash to display file contents',
            'When summarizing your actions, output plain text directly; do not use bash to display what you did',
        ];
    }
}
