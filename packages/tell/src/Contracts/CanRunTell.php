<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Runtime\TellRun;
use Cognesy\Tell\TellProgress;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\TellResult;
use Generator;

interface CanRunTell
{
    public function run(TellRequest $request): TellResult;

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    public function stream(TellRequest $request): Generator;

    /**
     * Starts a run and returns a handle that owns its outcome, so a caller that
     * stops iterating early still gets a result and an abandoned run is
     * reported rather than lost.
     */
    public function start(TellRequest $request): TellRun;
}
