<?php declare(strict_types=1);

namespace Cognesy\Agents\Template;

use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Data\ValidationProblem;
use Cognesy\Agents\Template\Data\ValidationReport;
use Cognesy\Agents\Tool\Contracts\CanManageTools;

final readonly class AgentDefinitionValidator
{
    private AgentDefinitionReferenceRules $references;

    public function __construct(
        CanManageAgentCapabilities $capabilities,
        private ?CanManageTools $tools = null,
        private ?SkillLibrary $skills = null,
    ) {
        $this->references = new AgentDefinitionReferenceRules($capabilities, $tools);
    }

    public function validate(AgentDefinition $definition): ValidationReport {
        $problems = [
            ...$this->validateRequiredFields($definition),
            ...$this->validateReferences($definition),
            ...$this->validateSkills($definition),
            ...$this->validateBudget($definition->budget),
        ];
        return new ValidationReport(...$problems);
    }

    /** @return list<ValidationProblem> */
    private function validateRequiredFields(AgentDefinition $definition): array {
        $problems = [];
        if (!AgentDefinitionName::isValid($definition->name)) {
            $problems[] = new ValidationProblem('name', 'Must match /^[a-z0-9][a-z0-9_-]*$/.');
        }
        if (trim($definition->description) === '') {
            $problems[] = new ValidationProblem('description', 'Must not be empty.');
        }
        if (trim($definition->systemPrompt) === '') {
            $problems[] = new ValidationProblem('systemPrompt', 'Must not be empty.');
        }
        return $problems;
    }

    /** @return list<ValidationProblem> */
    private function validateReferences(AgentDefinition $definition): array {
        $problems = [];
        foreach ($this->references->unknownCapabilities($definition)->all() as $name) {
            $problems[] = new ValidationProblem('capabilities', "Unknown capability '{$name}'.");
        }
        if ($this->references->requiresToolRegistry($definition) && $this->toolsMissing()) {
            $problems[] = new ValidationProblem('tools', 'A tool registry is required.');
            return $problems;
        }
        foreach ($this->references->unknownTools($definition)->all() as $name) {
            $problems[] = new ValidationProblem('tools', "Unknown tool '{$name}'.");
        }
        return $problems;
    }

    /** @return list<ValidationProblem> */
    private function validateSkills(AgentDefinition $definition): array {
        if ($this->skills === null || $definition->skills === null) {
            return [];
        }
        $problems = [];
        foreach ($definition->skills->all() as $name) {
            if (!$this->skills->hasSkill($name)) {
                $problems[] = new ValidationProblem('skills', "Unknown skill '{$name}'.");
            }
        }
        return $problems;
    }

    /** @return list<ValidationProblem> */
    private function validateBudget(?ExecutionBudget $budget): array {
        if ($budget === null) {
            return [];
        }
        $values = [
            'budget.maxSteps' => $budget->maxSteps,
            'budget.maxTokens' => $budget->maxTokens,
            'budget.maxSeconds' => $budget->maxSeconds,
            'budget.maxCost' => $budget->maxCost,
        ];
        $problems = [];
        foreach ($values as $field => $value) {
            if ($value !== null && $value < 0) {
                $problems[] = new ValidationProblem($field, 'Must be non-negative.');
            }
        }
        return $problems;
    }

    private function toolsMissing(): bool {
        return $this->tools === null;
    }
}
