<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Observation;

use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;

/** Records one terminal Tell outcome and exposes its current trace health. */
interface CanRecordTellTrace
{
    public function recordOutcome(
        TellTermination $termination,
        TellPublication $publication,
        TellExecutionMode $requestedMode,
    ): void;

    public function reference(): TellTraceReference;
}
