<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Data;

final readonly class ValidationReport
{
    /** @var list<ValidationProblem> */
    private array $problems;

    public function __construct(ValidationProblem ...$problems) {
        $this->problems = $problems;
    }

    public static function valid(): self {
        return new self();
    }

    public function isValid(): bool {
        return $this->problems === [];
    }

    /** @return list<ValidationProblem> */
    public function problems(): array {
        return $this->problems;
    }

    /** @return list<array{field: string, message: string}> */
    public function toArray(): array {
        return array_map(
            static fn (ValidationProblem $problem): array => $problem->toArray(),
            $this->problems,
        );
    }
}
