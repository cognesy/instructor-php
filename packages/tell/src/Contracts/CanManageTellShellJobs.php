<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Shell\TellShellJobOutput;
use Cognesy\Tell\Shell\TellShellJobRequest;
use Cognesy\Tell\Shell\TellShellJobSnapshot;

interface CanManageTellShellJobs
{
    public function start(TellShellJobRequest $request): TellShellJobSnapshot;

    public function status(string $jobId): TellShellJobSnapshot;

    public function read(string $jobId, int $after = 0): TellShellJobOutput;

    public function wait(string $jobId, int $timeoutMs = 30_000): TellShellJobSnapshot;

    public function cancel(string $jobId): TellShellJobSnapshot;

    /** @return list<TellShellJobSnapshot> */
    public function all(): array;
}
