<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Observation;

use Cognesy\Tell\Core\Contract\Observation\CanRecordTellTrace;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;

final readonly class NullTellTrace implements CanRecordTellTrace
{
    #[\Override]
    public function recordOutcome(
        TellTermination $termination,
        TellPublication $publication,
        TellExecutionMode $requestedMode,
    ): void {}

    #[\Override]
    public function reference(): TellTraceReference {
        return TellTraceReference::disabled();
    }
}
