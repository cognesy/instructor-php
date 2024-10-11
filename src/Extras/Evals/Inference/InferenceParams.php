<?php

namespace Cognesy\Instructor\Extras\Evals\Inference;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanMapValues;

class InferenceParams implements CanMapValues {
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
}
