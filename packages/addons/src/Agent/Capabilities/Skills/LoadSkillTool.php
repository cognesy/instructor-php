<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Skills;

use Cognesy\Addons\Agent\Tools\BaseTool;

class LoadSkillTool extends BaseTool
{
    private SkillLibrary $library;

    public function __construct(?SkillLibrary $library = null) {
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
        );

        $this->library = $library ?? new SkillLibrary();
    }

    public static function withLibrary(SkillLibrary $library): self {
        return new self($library);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $skill_name = $args['skill_name'] ?? $args[0] ?? null;
        $list_skills = $args['list_skills'] ?? $args[1] ?? false;

        if ($list_skills || $skill_name === null) {
            return $this->library->renderSkillList();
        }

        if (!$this->library->hasSkill($skill_name)) {
            $available = $this->library->renderSkillList();
            return "Skill '{$skill_name}' not found.\n\n{$available}";
        }

        $skill = $this->library->getSkill($skill_name);
        if ($skill === null) {
            return "Error: Failed to load skill '{$skill_name}'";
        }

        return $skill->render();
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'skill_name' => [
                            'type' => 'string',
                            'description' => 'Name of the skill to load',
                        ],
                        'list_skills' => [
                            'type' => 'boolean',
                            'description' => 'If true, list all available skills instead of loading one',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }
}
