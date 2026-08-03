<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Polyglot\Inference\Config\LLMConfig;

final readonly class LLMConfigProfile
{
    public function __construct(
        public string $driver,
        public string $model,
        public int $maxTokens,
        public int $contextLength,
        public int $maxOutputLength,
    ) {}

    public static function fromConfig(LLMConfig $config): self {
        return new self(
            driver: $config->driver,
            model: $config->model,
            maxTokens: $config->maxTokens,
            contextLength: $config->contextLength,
            maxOutputLength: $config->maxOutputLength,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'driver' => $this->driver,
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'contextLength' => $this->contextLength,
            'maxOutputLength' => $this->maxOutputLength,
        ];
    }
}
