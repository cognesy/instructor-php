<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Cognesy\Agents\CanControlAgentLoop;
use Override;

/** Executes eval sessions in process through a fresh agent loop factory. */
final readonly class LocalAgentTarget implements CanRunAgentEvalTarget
{
    /** @param Closure(): CanControlAgentLoop $factory */
    private function __construct(
        private Closure $factory,
        private EvalTracePolicy $policy,
    ) {}

    /** @param Closure(): CanControlAgentLoop $factory */
    public static function fromFactory(Closure $factory, ?EvalTracePolicy $policy = null): self {
        return new self($factory, $policy ?? EvalTracePolicy::safe());
    }

    #[Override]
    public function open(?EvalSessionRequest $request = null): CanUseAgentEvalSession {
        $loop = ($this->factory)();
        return new LocalEvalSession($loop, $this->policy);
    }
}
