<?php

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Evals\Contracts\CanMapValues;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class InferenceCaseParams implements CanMapValues {
    public string $preset;
    public bool $isStreamed;
    public OutputMode $mode;

    public static function map(array $values) : static {
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
