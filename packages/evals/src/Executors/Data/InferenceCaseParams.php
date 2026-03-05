<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Evals\Contracts\CanMapValues;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * @implements CanMapValues<self>
 */
class InferenceCaseParams implements CanMapValues {
    public string $connection = 'openai';
    public bool $isStreamed = false;
    public OutputMode $mode = OutputMode::Text;
    public ?LLMConfig $llmConfig = null;

    #[\Override]
    public static function map(array $values) : self {
        $instance = new self();
        $instance->mode = $values['mode'] ?? OutputMode::Text;
        $instance->connection = match (true) {
            isset($values['connection']) => (string) $values['connection'],
            isset($values['preset']) => (string) $values['preset'],
            default => 'openai',
        };
        $instance->isStreamed = $values['isStreamed'] ?? false;
        $instance->llmConfig = self::mapLlmConfig($values['llmConfig'] ?? null);
        return $instance;
    }

    public function toArray() : array {
        return [
            'connection' => $this->connection,
            'isStreamed' => $this->isStreamed,
            'mode' => $this->mode,
            'llmConfig' => $this->llmConfig,
        ];
    }

    public function __toString() : string {
        return $this->connection.'::'.$this->mode->value.'::'.($this->isStreamed ? 'streamed' : 'sync');
    }

    private static function mapLlmConfig(mixed $value) : ?LLMConfig {
        return match (true) {
            $value instanceof LLMConfig => $value,
            is_array($value) => LLMConfig::fromArray($value),
            $value === null => null,
            default => throw new \InvalidArgumentException('Inference case LLM config must be array, LLMConfig, or null.'),
        };
    }
}
