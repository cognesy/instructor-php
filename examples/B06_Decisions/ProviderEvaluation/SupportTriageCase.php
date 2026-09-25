<?php

declare(strict_types=1);

namespace Cognesy\Examples\DecisionEvaluation;

final readonly class SupportTriageCase
{
    public function __construct(
        public string $id,
        public string $input,
        public bool $refundRequested,
        public string $department,
        public int $urgency,
    ) {}
}
