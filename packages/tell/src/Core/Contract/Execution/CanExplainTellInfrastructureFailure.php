<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Execution;

use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;
use Throwable;

interface CanExplainTellInfrastructureFailure extends Throwable
{
    public function failureCode(): string;

    public function termination(): ?TellTermination;

    public function publication(): ?TellPublication;

    public function trace(): ?TellTraceReference;
}
