<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Metadata;

final readonly class AgentDefinition
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public ?string $label = null,
        public LLMConfig|string|null $llmConfig = null,
        public ?NameList $tools = null,
        public ?NameList $toolsDeny = null,
        public ?NameList $skills = null,
        public ?string $blueprint = null,
        public ?string $blueprintClass = null,
        public ?int $maxSteps = null,
        public ?int $maxTokens = null,
        public ?int $timeoutSec = null,
        public NameList $capabilities = new NameList(),
        public ?Metadata $metadata = null,
    ) {}

    // ACCESSORS ////////////////////////////////////////////////////
    public function inheritsAllTools(): bool {
        return $this->tools === null;
    }

    public function label(): string {
        return $this->label ?? $this->name;
    }

    public function hasSkills(): bool {
        return $this->skills !== null && !$this->skills->isEmpty();
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'label' => $this->label(),
            'description' => $this->description,
            'systemPrompt' => $this->systemPrompt,
            'llmConfig' => $this->serializeLlmConfig(),
            'tools' => $this->tools?->toArray(),
            'toolsDeny' => $this->toolsDeny?->toArray(),
            'skills' => $this->skills?->toArray(),
            'blueprint' => $this->blueprint,
            'blueprintClass' => $this->blueprintClass,
            'maxSteps' => $this->maxSteps,
            'maxTokens' => $this->maxTokens,
            'timeoutSec' => $this->timeoutSec,
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
            llmConfig: self::deserializeLlmConfig($data['llmConfig'] ?? null),
            tools: NameList::fromArray($data['tools'] ?? []),
            toolsDeny: NameList::fromArray($data['toolsDeny'] ?? []),
            skills: NameList::fromArray($data['skills'] ?? []),
            blueprint: $data['blueprint'] ?? null,
            blueprintClass: $data['blueprintClass'] ?? null,
            maxSteps: $data['maxSteps'] ?? null,
            maxTokens: $data['maxTokens'] ?? null,
            timeoutSec: $data['timeoutSec'] ?? null,
            capabilities: NameList::fromArray($data['capabilities'] ?? []),
            metadata: Metadata::fromArray($data['metadata'] ?? []),
        );
    }

    // PRIVATE HELPERS //////////////////////////////////////////////

    private static function deserializeLlmConfig(mixed $param) : LlmConfig|string|null {
        return match(true) {
            is_string($param) => LLMProvider::using($param),
            is_array($param) => LLMConfig::fromArray($param),
            default => null,
        };
    }

    private function serializeLlmConfig(): array|string|null {
        return match(true) {
            is_string($this->llmConfig) => $this->llmConfig,
            $this->llmConfig instanceof LLMConfig => $this->llmConfig->toArray(),
            default => null,
        };
    }
}
