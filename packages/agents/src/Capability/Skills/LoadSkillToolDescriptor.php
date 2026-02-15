<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class LoadSkillToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'load_skill',
            description: <<<'DESC'
Load a skill for specialized knowledge or workflows.

Examples:
- list_skills=true → see all available skills
- skill_name="code-review" → load code review expertise
- skill_name="api-design" → load API design best practices

Skills provide domain expertise, checklists, and step-by-step workflows.
DESC,
            metadata: [
                'name' => 'load_skill',
                'summary' => 'Load or list available skills from the skill library.',
                'namespace' => 'skills',
                'tags' => ['skills', 'knowledge', 'workflow'],
            ],
            instructions: [
                'parameters' => [
                    'skill_name' => 'Name of the skill to load.',
                    'list_skills' => 'Set true to list available skills.',
                ],
                'returns' => 'Rendered skill content or list of skills.',
            ],
        );
    }
}
