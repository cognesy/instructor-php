<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Stringable;

final class ToStringReducer implements Reducer
{
    public function __construct(
        private string $separator = '',
        private string $prefix = '',
        private string $suffix = '',
    ) {}

    #[\Override]
    public function init(): mixed {
        return '';
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $piece = $this->stringify($reducible);
        if ($accumulator === '') {
            return $piece;
        }
        return $accumulator . $this->separator . $piece;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->prefix . $accumulator . $this->suffix;
    }

    // INTERNAL /////////////////////////////////////////////////////

    private function stringify(mixed $value): string {
        return match (true) {
            $value instanceof Stringable => (string) $value,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}

