<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Tool\Tools\StateAwareTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class LoadSkillTool extends StateAwareTool
{
    private SkillLibrary $library;
    private ?SkillForkExecutor $forkExecutor;
    private ?SkillPreprocessor $preprocessor;

    public function __construct(
        SkillLibrary $library,
        ?SkillForkExecutor $forkExecutor = null,
        ?SkillPreprocessor $preprocessor = null,
    ) {
        parent::__construct(new LoadSkillToolDescriptor());

        $this->library = $library;
        $this->forkExecutor = $forkExecutor;
        $this->preprocessor = $preprocessor;
    }

    public static function fromLibrary(
        SkillLibrary $library,
        ?SkillForkExecutor $forkExecutor = null,
        ?SkillPreprocessor $preprocessor = null,
    ): self {
        return new self($library, $forkExecutor, $preprocessor);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $skill_name = $this->arg($args, 'skill_name', 0);
        $list_skills = $this->arg($args, 'list_skills', 1, false);
        $arguments = $this->arg($args, 'arguments', 2);

        if ($list_skills || $skill_name === null) {
            return $this->library->renderSkillList(userInvocable: true);
        }

        if (!$this->library->hasSkill($skill_name)) {
            $available = $this->library->renderSkillList(userInvocable: true);
            return "Skill '{$skill_name}' not found.\n\n{$available}";
        }

        $skill = $this->library->getSkill($skill_name);
        if ($skill === null) {
            return "Error: Failed to load skill '{$skill_name}'";
        }

        // Shell preprocessing: execute !`command` patterns in body
        if ($this->preprocessor !== null && $this->preprocessor->hasCommands($skill->body)) {
            $skill = new Skill(
                name: $skill->name,
                description: $skill->description,
                body: $this->preprocessor->process($skill->body),
                path: $skill->path,
                license: $skill->license,
                compatibility: $skill->compatibility,
                metadata: $skill->metadata,
                allowedTools: $skill->allowedTools,
                disableModelInvocation: $skill->disableModelInvocation,
                userInvocable: $skill->userInvocable,
                argumentHint: $skill->argumentHint,
                model: $skill->model,
                context: $skill->context,
                agent: $skill->agent,
                resources: $skill->resources,
            );
        }

        // Fork execution: run skill in isolated subagent
        if ($skill->context === 'fork' && $this->forkExecutor !== null) {
            return $this->forkExecutor->execute(
                skill: $skill,
                arguments: $arguments,
                parentLLMConfig: $this->agentState?->llmConfig(),
            );
        }

        return $skill->render($arguments);
    }

    #[\Override]
    public function toToolSchema(): \Cognesy\Polyglot\Inference\Data\ToolDefinition {
        return \Cognesy\Polyglot\Inference\Data\ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('skill_name', 'Name of the skill to load'),
                    JsonSchema::boolean('list_skills', 'If true, list all available skills instead of loading one'),
                    JsonSchema::string('arguments', 'Arguments to pass to the skill'),
                ])
        )->toArray());
    }
}
