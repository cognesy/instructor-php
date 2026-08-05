<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class JudgeRequest
{
    public function __construct(
        public string $criterion,
        public string $output,
        public string $input = '',
        public ?string $reference = null,
    ) {}
}
