<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Data;

use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
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
        public ?ExecutionBudget $budget = null,
        public ?Metadata $metadata = null,
    ) {}

    // ACCESSORS ////////////////////////////////////////////////////

    public function label(): string {
        return $this->label ?? $this->name;
    }

    public function budget(): ExecutionBudget {
        return $this->budget ?? ExecutionBudget::unlimited();
    }

    public function inheritsAllTools(): bool {
        return $this->tools === null;
    }

    public function hasSkills(): bool {
        return $this->skills !== null && !$this->skills->isEmpty();
    }

    // SERIALIZATION ////////////////////////////////////////////////

    /** @return array<string, mixed> */
    public function canonicalArray(): array {
        return [
            'name' => $this->name,
            'label' => $this->canonicalLabel(),
            'description' => $this->description,
            'systemPrompt' => trim($this->systemPrompt),
            'llmConfig' => $this->serializeLLMConfig(),
            'tools' => $this->canonicalNameList($this->tools),
            'toolsDeny' => $this->canonicalNameList($this->toolsDeny),
            'skills' => $this->canonicalNameList($this->skills),
            'budget' => $this->canonicalBudget(),
            'capabilities' => $this->capabilities->toArray(),
            'metadata' => $this->canonicalMetadata(),
        ];
    }

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
            'metadata' => $this->metadata?->toArray(),
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
            tools: self::deserializeOptionalNameList($data, 'tools'),
            toolsDeny: self::deserializeOptionalNameList($data, 'toolsDeny'),
            skills: self::deserializeOptionalNameList($data, 'skills'),
            budget: self::deserializeBudget($data['budget'] ?? null),
            metadata: Metadata::fromArray($data['metadata'] ?? []),
        );
    }

    // PRIVATE HELPERS //////////////////////////////////////////////

    private static function deserializeLLMConfig(mixed $param): LLMConfig|string|null {
        return match (true) {
            is_string($param) => $param,
            is_array($param) => LLMConfig::fromArray($param),
            default => null,
        };
    }

    private static function deserializeBudget(mixed $param): ?ExecutionBudget {
        return match (true) {
            is_array($param) => ExecutionBudget::fromArray($param),
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    private static function deserializeOptionalNameList(array $data, string $key): ?NameList {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return match (true) {
            $data[$key] instanceof NameList => $data[$key],
            is_array($data[$key]) => NameList::fromArray($data[$key]),
            default => NameList::fromArray([]),
        };
    }

    private function canonicalLabel(): ?string {
        return match (true) {
            $this->label === null => null,
            trim($this->label) === '' => null,
            $this->label === $this->name => null,
            default => $this->label,
        };
    }

    /** @return list<string>|null */
    private function canonicalNameList(?NameList $names): ?array {
        return match (true) {
            $names === null => null,
            $names->isEmpty() => null,
            default => $names->toArray(),
        };
    }

    /** @return array<string, mixed>|null */
    private function canonicalBudget(): ?array {
        return match (true) {
            $this->budget === null => null,
            $this->budget->isEmpty() => null,
            default => $this->budget->toArray(),
        };
    }

    /** @return array<string, mixed>|null */
    private function canonicalMetadata(): ?array {
        return match (true) {
            $this->metadata === null => null,
            $this->metadata->isEmpty() => null,
            default => $this->metadata->toArray(),
        };
    }

    private function serializeLLMConfig(): array|string|null {
        return match (true) {
            is_string($this->llmConfig) => $this->llmConfig,
            $this->llmConfig instanceof LLMConfig => $this->llmConfig->toArray(),
            default => null,
        };
    }
}
