<?php

namespace Cognesy\Instructor\Extras\Evals\Mappings;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanMapValues;

class ConnectionModes implements CanMapValues {
    public Mode $mode;
    public string $connection;
    public bool $isStreaming;

    public static function map(array $values) : static {
        $instance = new self();
        $instance->mode = $values['mode'] ?? Mode::Text;
        $instance->connection = $values['connection'] ?? 'openai';
        $instance->isStreaming = $values['isStreaming'] ?? false;
        return $instance;
    }
}
