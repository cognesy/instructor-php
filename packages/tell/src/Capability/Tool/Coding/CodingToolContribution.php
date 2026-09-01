<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Tool\Coding;

use Cognesy\Agents\Capability\Bash\BashPolicy;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Agent\TellAgentAssembly;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Data\TellExecutionPolicy;

final readonly class CodingToolContribution implements CanContributeTellAgent
{
    public function __construct(private TellPaths $paths) {}

    #[\Override]
    public function contribute(TellAgentAssembly $assembly): void {
        $policy = $assembly->request->policy ?? TellExecutionPolicy::defaults();
        $blobs = $policy->spillsToolOutput()
            ? $this->paths->blobsFor($assembly->request->directory)
            : null;
        $assembly->capabilities->register(TellCodingTools::capabilityName(), new TellCodingTools(
            $assembly->request->directory,
            $this->bashPolicy($policy),
            $blobs,
        ));
    }

    private function bashPolicy(TellExecutionPolicy $policy): BashPolicy {
        $bytes = $policy->spillsToolOutput() ? $policy->maxSpillBytes : $policy->maxToolOutputChars;

        return new BashPolicy(
            maxOutputChars: $bytes,
            headChars: intdiv($bytes, 2),
            tailChars: $bytes - intdiv($bytes, 2),
            timeout: max(1, (int) ceil($policy->timeoutMs / 1_000)),
            stdoutLimitBytes: $bytes,
            stderrLimitBytes: $bytes,
        );
    }
}
