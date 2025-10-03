<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Evals\Contracts\CanMapValues;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * @implements CanMapValues<self>
 */
class InferenceCaseParams implements CanMapValues {
    public string $preset = 'openai';
    public bool $isStreamed = false;
    public OutputMode $mode = OutputMode::Text;

    #[\Override]
    public static function map(array $values) : self {
        $instance = new self();
        $instance->mode = $values['mode'] ?? OutputMode::Text;
        $instance->preset = $values['preset'] ?? 'openai';
        $instance->isStreamed = $values['isStreamed'] ?? false;
        return $instance;
    }

    public function toArray() : array {
        return [
            'preset' => $this->preset,
            'isStreamed' => $this->isStreamed,
            'mode' => $this->mode,
        ];
    }

    public function __toString() : string {
        return $this->preset.'::'.$this->mode->value.'::'.($this->isStreamed ? 'streamed' : 'sync');
    }
}
