<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Spec;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\SubagentDefinition;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use InvalidArgumentException;

final readonly class AgentSpec implements SubagentDefinition
{
    /**
     * @param string $name Unique identifier (lowercase, hyphens)
     * @param string $description When to use this agent
     * @param string $systemPrompt Custom system prompt for agent
     * @param array<string>|null $tools Tool names (null = inherit all from parent)
     * @param LLMConfig|string|null $model LLMConfig object, preset name, 'inherit', or null
     * @param array<string>|null $skills Skill names to preload
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public ?array $tools = null,
        public LLMConfig|string|null $model = null,
        public ?array $skills = null,
        public array $metadata = [],
    ) {
        $this->validateName($name);
        $this->validateDescription($description);
        $this->validateSystemPrompt($systemPrompt);
    }

    // VALIDATION ///////////////////////////////////////////////////

    private function validateName(string $name): void {
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid agent name '{$name}': must start with lowercase letter and contain only lowercase letters, numbers, and hyphens"
            );
        }
    }

    private function validateDescription(string $description): void {
        if (trim($description) === '') {
            throw new InvalidArgumentException("Agent description cannot be empty");
        }
    }

    private function validateSystemPrompt(string $systemPrompt): void {
        if (trim($systemPrompt) === '') {
            throw new InvalidArgumentException("Agent system prompt cannot be empty");
        }
    }

    // TOOL MANAGEMENT //////////////////////////////////////////////

    #[\Override]
    public function inheritsAllTools(): bool {
        return $this->tools === null;
    }

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function description(): string {
        return $this->description;
    }

    #[\Override]
    public function systemPrompt(): string {
        return $this->systemPrompt;
    }

    #[\Override]
    public function tools(): ?array {
        return $this->tools;
    }

    public function isToolAllowed(string $toolName): bool {
        if ($this->inheritsAllTools()) {
            return true;
        }
        return in_array($toolName, $this->tools ?? [], true);
    }

    /**
     * Filter parent's tools to only those allowed by this spec.
     */
    #[\Override]
    public function filterTools(Tools $parentTools): Tools {
        if ($this->inheritsAllTools()) {
            return $parentTools;
        }

        $filtered = [];
        foreach ($parentTools->all() as $tool) {
            if ($this->isToolAllowed($tool->name())) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    /**
     * Validate that all specified tools exist in parent tools.
     *
     * @return array<string> List of validation errors (empty if valid)
     */
    public function validate(Tools $parentTools): array {
        $errors = [];

        if ($this->inheritsAllTools()) {
            return $errors; // Nothing to validate
        }

        foreach ($this->tools ?? [] as $toolName) {
            if (!$parentTools->has($toolName)) {
                $errors[] = "Unknown tool '{$toolName}' in agent '{$this->name}'";
            }
        }

        return $errors;
    }

    // SKILL MANAGEMENT /////////////////////////////////////////////

    #[\Override]
    public function hasSkills(): bool {
        return $this->skills !== null && count($this->skills) > 0;
    }

    #[\Override]
    public function skills(): ?array {
        return $this->skills;
    }

    // MODEL RESOLUTION /////////////////////////////////////////////

    public function shouldInheritModel(): bool {
        return $this->model === 'inherit';
    }

    /**
     * Resolve the LLM provider for this agent.
     *
     * @param LLMProvider|null $parentProvider Parent's LLM provider for inheritance
     * @return LLMProvider Resolved LLM provider
     */
    #[\Override]
    public function resolveLlmProvider(?LLMProvider $parentProvider = null): LLMProvider {
        return match(true) {
            $this->model instanceof LLMConfig => LLMProvider::new()->withConfig($this->model),
            $this->model === 'inherit' => $parentProvider ?? LLMProvider::new(),
            is_string($this->model) => LLMProvider::using($this->model),
            default => LLMProvider::new(),
        };
    }

    // SERIALIZATION ////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'systemPrompt' => $this->systemPrompt,
            'tools' => $this->tools,
            'model' => $this->serializeModel(),
            'skills' => $this->skills,
            'metadata' => $this->metadata,
        ];
    }

    private function serializeModel(): array|string|null {
        if ($this->model instanceof LLMConfig) {
            return $this->model->toArray();
        }
        return $this->model;
    }
}
