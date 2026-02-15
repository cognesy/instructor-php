<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class EditFileToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'edit_file',
            description: <<<'DESC'
Edit a file by replacing exact string matches. old_string must match exactly including whitespace.

Examples:
- Fix typo: old_string="teh", new_string="the"
- Change function: old_string="function old()", new_string="function new()"
- Update config: old_string='"debug": false', new_string='"debug": true'
- Rename all: old_string="OldClass", new_string="NewClass", replace_all=true

IMPORTANT: Include enough context in old_string to make it unique. If multiple matches exist, use replace_all=true or provide more surrounding code.
DESC,
            metadata: [
                'name' => 'edit_file',
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
                'returns' => 'Success message with replacement count or explicit error.',
            ],
        );
    }
}
