<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;

/** Result of one deterministic or model-graded eval assertion. */
final readonly class AssertionResult
{
    public function __construct(
        private string $name,
        private float $score,
        private AssertionSeverity $severity = AssertionSeverity::Gate,
        private ?float $threshold = null,
        private string $message = '',
        private ?string $label = null,
    ) {
        self::validateScore($score, 'score');
        if ($threshold !== null) {
            self::validateScore($threshold, 'threshold');
        }
    }

    public static function pass(string $name, string $message = ''): self {
        return new self($name, 1.0, message: $message);
    }

    public static function fail(string $name, string $message = ''): self {
        return new self($name, 0.0, message: $message);
    }

    public function withSeverity(AssertionSeverity $severity): self {
        return new self($this->name, $this->score, $severity, $this->threshold, $this->message, $this->label);
    }

    public function withScore(float $score, string $message = ''): self {
        return new self($this->name, $score, $this->severity, $this->threshold, $message, $this->label);
    }

    public function withThreshold(float $threshold): self {
        return new self($this->name, $this->score, $this->severity, $threshold, $this->message, $this->label);
    }

    public function withLabel(string $label): self {
        return new self($this->name, $this->score, $this->severity, $this->threshold, $this->message, $label);
    }

    public function passed(): bool {
        return $this->score >= ($this->threshold ?? 1.0);
    }

    public function name(): string {
        return $this->name;
    }

    public function score(): float {
        return $this->score;
    }

    public function severity(): AssertionSeverity {
        return $this->severity;
    }

    public function threshold(): ?float {
        return $this->threshold;
    }

    public function message(): string {
        return $this->message;
    }

    public function label(): ?string {
        return $this->label;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'score' => $this->score,
            'threshold' => $this->threshold,
            'severity' => $this->severity->value,
            'passed' => $this->passed(),
            'message' => $this->message,
        ];
    }

    private static function validateScore(float $value, string $field): void {
        if (!is_finite($value) || $value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException("Assertion {$field} must be between 0 and 1.");
        }
    }
}
