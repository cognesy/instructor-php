<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

/**
 * A judge request against a specific target run. `run` is required, not
 * optional-with-default: a trajectory-aware judge receiving a request with no
 * trajectory is a programming error and should not be representable.
 */
final readonly class JudgeRequest
{
    public function __construct(
        public string $criterion,
        public string $output,
        public AgentRun $run,
        public string $input = '',
        public ?string $reference = null,
    ) {}
}
