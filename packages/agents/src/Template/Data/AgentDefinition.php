<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Data;

use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Utils\Metadata;

final readonly class AgentDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public ?string $label = null,
        public LLMConfig|string|null $llmConfig = null,
        public NameList $capabilities = new NameList(),
        public ?NameList $tools = null,
        public ?NameList $toolsDeny = null,
        public ?NameList $skills = null,
        public ?AgentBudget $budget = null,
        public ?Metadata $metadata = null,
    ) {}

    // ACCESSORS ////////////////////////////////////////////////////

    public function label(): string {
        return $this->label ?? $this->name;
    }

    public function budget(): AgentBudget {
        return $this->budget ?? AgentBudget::unlimited();
    }

    public function inheritsAllTools(): bool {
        return $this->tools === null;
    }

    public function hasSkills(): bool {
        return $this->skills !== null && !$this->skills->isEmpty();
    }

    // SERIALIZATION ////////////////////////////////////////////////

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'label' => $this->label(),
            'description' => $this->description,
            'systemPrompt' => $this->systemPrompt,
            'llmConfig' => $this->serializeLLMConfig(),
            'tools' => $this->tools?->toArray(),
            'toolsDeny' => $this->toolsDeny?->toArray(),
            'skills' => $this->skills?->toArray(),
            'budget' => $this->budget?->toArray(),
            'capabilities' => $this->capabilities->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        $label = $data['label'] ?? $data['title'] ?? null;
        $label = is_string($label) && trim($label) !== '' ? $label : null;

        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            systemPrompt: $data['systemPrompt'] ?? '',
            label: $label,
            llmConfig: self::deserializeLLMConfig($data['llmConfig'] ?? null),
            capabilities: NameList::fromArray($data['capabilities'] ?? []),
            tools: NameList::fromArray($data['tools'] ?? []),
            toolsDeny: NameList::fromArray($data['toolsDeny'] ?? []),
            skills: NameList::fromArray($data['skills'] ?? []),
            budget: self::deserializeBudget($data['budget'] ?? null),
            metadata: Metadata::fromArray($data['metadata'] ?? []),
        );
    }

    // PRIVATE HELPERS //////////////////////////////////////////////

    private static function deserializeLLMConfig(mixed $param) : LLMConfig|string|null {
        return match(true) {
            is_string($param) => $param,
            is_array($param) => LLMConfig::fromArray($param),
            default => null,
        };
    }

    private static function deserializeBudget(mixed $param): ?AgentBudget {
        return match (true) {
            is_array($param) => AgentBudget::fromArray($param),
            default => null,
        };
    }

    private function serializeLLMConfig(): array|string|null {
        return match(true) {
            is_string($this->llmConfig) => $this->llmConfig,
            $this->llmConfig instanceof LLMConfig => $this->llmConfig->toArray(),
            default => null,
        };
    }
}
