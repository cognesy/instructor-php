<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Events\Event;

class InferenceEvent extends Event
{
    protected function stringValue(string $key): ?string {
        $value = $this->value($key);

        return match (true) {
            is_string($value) && $value !== '' => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }

    protected function intValue(string $key): ?int {
        $value = $this->value($key);

        return match (true) {
            is_int($value) => $value,
            is_numeric($value) => (int) $value,
            default => null,
        };
    }

    protected function floatValue(string $key): ?float {
        $value = $this->value($key);

        return match (true) {
            is_float($value), is_int($value) => (float) $value,
            is_numeric($value) => (float) $value,
            default => null,
        };
    }

    protected function boolValue(string $key): ?bool {
        $value = $this->value($key);

        return match (true) {
            is_bool($value) => $value,
            default => null,
        };
    }

    private function value(string $key): mixed {
        return match (true) {
            is_array($this->data) => $this->data[$key] ?? null,
            default => null,
        };
    }
}
