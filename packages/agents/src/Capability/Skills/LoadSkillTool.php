<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class LoadSkillTool extends SimpleTool
{
    private SkillLibrary $library;

    public function __construct(SkillLibrary $library) {
        parent::__construct(new LoadSkillToolDescriptor());

        $this->library = $library;
    }

    public static function withLibrary(SkillLibrary $library): self {
        return new self($library);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $skill_name = $this->arg($args, 'skill_name', 0);
        $list_skills = $this->arg($args, 'list_skills', 1, false);

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
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('skill_name', 'Name of the skill to load'),
                    JsonSchema::boolean('list_skills', 'If true, list all available skills instead of loading one'),
                ])
        )->toArray();
    }
}
