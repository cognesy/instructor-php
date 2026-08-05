<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use InvalidArgumentException;

final readonly class EvalMatch
{
    private function __construct(
        private string $mode,
        private mixed $value,
    ) {}

    public static function partial(array $value): self {
        return new self('partial', $value);
    }

    public static function regex(string $pattern): self {
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException("Invalid regular expression: {$pattern}");
        }
        return new self('regex', $pattern);
    }

    public static function satisfies(Closure $predicate): self {
        return new self('predicate', $predicate);
    }

    public function matches(mixed $actual): bool {
        return match ($this->mode) {
            'partial' => EvalMatcher::partial($this->value, $actual),
            'regex' => preg_match($this->value, self::stringify($actual)) === 1,
            'predicate' => (bool) ($this->value)($actual),
            default => false,
        };
    }

    private static function stringify(mixed $value): string {
        return is_string($value) ? $value : (json_encode($value) ?: '');
    }
}
