<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools;

use Cognesy\Addons\Agent\Skills\SkillLibrary;

class LoadSkillTool extends BaseTool
{
    private SkillLibrary $library;

    public function __construct(?SkillLibrary $library = null) {
        parent::__construct(
            name: 'load_skill',
            description: 'Load a skill definition to get specialized knowledge or instructions. Skills provide domain expertise, workflows, and best practices. Use list_skills parameter to see available skills first.',
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
}
