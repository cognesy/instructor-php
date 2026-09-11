<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Polyglot\Inference\Config\LLMConfig;

final readonly class LLMConfigProfile
{
    public function __construct(
        public string $driver,
        public string $model,
        public int $maxTokens,
    ) {}

    public static function fromConfig(LLMConfig $config): self {
        return new self(
            driver: $config->driver,
            model: $config->model,
            maxTokens: $config->maxTokens,
        );
    }

    public static function fromArray(array $data): self {
        return new self(
            driver: is_string($data['driver'] ?? null) ? $data['driver'] : '',
            model: is_string($data['model'] ?? null) ? $data['model'] : '',
            maxTokens: is_int($data['maxTokens'] ?? null) ? $data['maxTokens'] : 0,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'driver' => $this->driver,
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
        ];
    }
}
