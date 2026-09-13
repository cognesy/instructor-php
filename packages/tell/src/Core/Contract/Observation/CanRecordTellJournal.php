<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Observation;

use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;

interface CanRecordTellJournal
{
    public function recordOutcome(
        TellTermination $termination,
        TellPublication $publication,
        TellTraceReference $trace,
        TellExecutionMode $requestedMode,
    ): void;
}
