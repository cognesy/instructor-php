<?php

namespace Cognesy\Instructor\Extras\Evals\Executors\Data;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanMapValues;

class InferenceCaseParams implements CanMapValues {
    public string $connection;
    public bool $isStreaming;
    public Mode $mode;

    public static function map(array $values) : static {
        $instance = new self();
        $instance->mode = $values['mode'] ?? Mode::Text;
        $instance->connection = $values['connection'] ?? 'openai';
        $instance->isStreaming = $values['isStreaming'] ?? false;
        return $instance;
    }

    public function toArray() : array {
        return [
            'connection' => $this->connection,
            'isStreaming' => $this->isStreaming,
            'mode' => $this->mode,
        ];
    }

    public function __toString() : string {
        return $this->connection.'::'.$this->mode->value.'::'.($this->isStreaming ? 'streamed' : 'sync');
    }
}
