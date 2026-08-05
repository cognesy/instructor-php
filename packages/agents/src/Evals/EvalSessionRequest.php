<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalSessionRequest
{
    public function __construct(
        public ?string $caseId = null,
        public ?string $description = null,
    ) {}
}
