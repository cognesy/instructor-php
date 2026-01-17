<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Definitions;

final readonly class AgentDefinition
{
    /**
     * @param array<int, string> $capabilities
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $version,
        public string $id,
        public string $name,
        public string $description,
        public string $systemPrompt,
        public ?string $blueprint,
        public ?string $blueprintClass,
        public AgentDefinitionLlm $llm,
        public AgentDefinitionExecution $execution,
        public AgentDefinitionTools $tools,
        public array $capabilities = [],
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'system_prompt' => $this->systemPrompt,
            'blueprint' => $this->blueprint,
            'blueprint_class' => $this->blueprintClass,
            'llm' => $this->llm->toArray(),
            'execution' => $this->execution->toArray(),
            'tools' => $this->tools->toArray(),
            'capabilities' => $this->capabilities,
            'metadata' => $this->metadata,
        ];
    }
}
