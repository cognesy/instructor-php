<?php

declare(strict_types=1);

namespace Cognesy\Tell\Protocol;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Contracts\CanWriteTellProtocolFrames;

/** One decoded request, one bounded run, and exactly one terminal frame. */
final readonly class OneRunTellProtocol implements CanRunTellProtocol
{
    public function __construct(private CanRunTell $runner) {}

    public function run(
        TellAgentProtocolRequest $request,
        CanWriteTellProtocolFrames $frames,
        ?CanProvideCancellationSignal $cancellation = null,
    ): int {
        $frames->identify($request->id);
        $stream = $this->runner->stream($request->request);
        foreach ($stream as $progress) {
            $frames->progress($progress);
        }
        $result = $stream->getReturn();

        if ($result->status() === ExecutionStatus::Completed) {
            $frames->success($result);

            return 0;
        }
        $reason = $result->state()->stopReason();
        if ($result->status() === ExecutionStatus::Stopped && $reason === StopReason::UserRequested) {
            $frames->cancelled($result);

            return 130;
        }
        if ($result->status() === ExecutionStatus::Stopped) {
            $frames->error('run_stopped', 'The Tell run stopped before completion.', $result, $reason?->value);

            return 1;
        }
        $frames->error('run_failed', 'The Tell run failed.', $result);

        return 1;
    }
}
