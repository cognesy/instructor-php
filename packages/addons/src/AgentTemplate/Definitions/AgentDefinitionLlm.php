<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Definitions;

final readonly class AgentDefinitionLlm
{
    public function __construct(
        public string $preset,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxOutputTokens = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_output_tokens' => $this->maxOutputTokens,
        ];
    }
}
