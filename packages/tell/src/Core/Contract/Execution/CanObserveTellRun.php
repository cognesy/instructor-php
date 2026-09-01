<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Execution;

use Cognesy\Tell\Data\TellDiagnostic;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellResult;
use Generator;

/** Owns the progress and terminal outcome of one Tell execution. */
interface CanObserveTellRun
{
    /** @return Generator<int, TellProgress, mixed, TellResult> */
    public function checkpoints(): Generator;

    public function isCommitted(): bool;

    public function result(): TellResult;

    /** @return list<TellDiagnostic> */
    public function diagnostics(): array;

    public function wait(): TellResult;
}
