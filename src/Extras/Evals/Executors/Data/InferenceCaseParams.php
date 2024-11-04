<?php

namespace Cognesy\Instructor\Extras\Evals\Executors\Data;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanMapValues;

class InferenceCaseParams implements CanMapValues {
    public string $connection;
    public bool $isStreamed;
    public Mode $mode;

    public static function map(array $values) : static {
        $instance = new self();
        $instance->mode = $values['mode'] ?? Mode::Text;
        $instance->connection = $values['connection'] ?? 'openai';
        $instance->isStreamed = $values['isStreamed'] ?? false;
        return $instance;
    }

    public function toArray() : array {
        return [
            'connection' => $this->connection,
            'isStreamed' => $this->isStreamed,
            'mode' => $this->mode,
        ];
    }

    public function __toString() : string {
        return $this->connection.'::'.$this->mode->value.'::'.($this->isStreamed ? 'streamed' : 'sync');
    }
}
