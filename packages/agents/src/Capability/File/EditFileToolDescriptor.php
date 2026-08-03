<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\Contracts\CanContributeToPrompt;
use Cognesy\Agents\Tool\ToolDescriptor;
use Override;

final readonly class EditFileToolDescriptor extends ToolDescriptor implements CanContributeToPrompt
{
    public function __construct(string $name = 'edit_file') {
        parent::__construct(
            name: $name,
            description: <<<'DESC'
Edit a file by replacing exact string matches. old_string must match exactly including whitespace.

Examples:
- Fix typo: old_string="teh", new_string="the"
- Change function: old_string="function old()", new_string="function new()"
- Update config: old_string='"debug": false', new_string='"debug": true'
- Rename all: old_string="OldClass", new_string="NewClass", replace_all=true

IMPORTANT: Include enough context in old_string to make it unique. If multiple matches exist, use replace_all=true or provide more surrounding code.
The file is processed as a bounded stream and replaced atomically only after match validation succeeds.
DESC,
            metadata: [
                'name' => $name,
                'summary' => 'Replace exact text in a file with optional replace-all mode.',
                'namespace' => 'file',
                'tags' => ['file', 'edit', 'replace'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'Target file path.',
                    'old_string' => 'Exact string to match, including whitespace.',
                    'new_string' => 'Replacement string.',
                    'replace_all' => 'Set true to replace all matches.',
                ],
                'returns' => 'Success with replacement count and source size, or an explicit no-change error.',
            ],
        );
    }

    #[Override]
    public function promptSnippet(): ?string {
        return 'Make precise edits to existing files';
    }

    #[Override]
    public function promptGuidelines(): array {
        return ['Use edit for precise changes; old text must match exactly'];
    }
}
