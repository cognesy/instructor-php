<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Closure;
use Cognesy\Tell\Contracts\CanApproveTellShellJobs;
use Cognesy\Tell\Shell\TellShellJobApproval;
use Cognesy\Tell\Shell\TellShellJobRequest;

final readonly class TellShellJobApprovals implements CanApproveTellShellJobs
{
    /** @param Closure(TellShellJobRequest): TellShellJobApproval $approval */
    private function __construct(private Closure $approval) {}

    public static function denyAll(): self
    {
        return new self(static fn (): TellShellJobApproval => TellShellJobApproval::deny());
    }

    /** Explicit opt-in intended for an already trusted embedding boundary. */
    public static function allowAll(): self
    {
        return new self(static fn (): TellShellJobApproval => TellShellJobApproval::allow());
    }

    /** @param callable(TellShellJobRequest): TellShellJobApproval $approval */
    public static function callback(callable $approval): self
    {
        return new self(Closure::fromCallable($approval));
    }

    public function approve(TellShellJobRequest $request): TellShellJobApproval
    {
        return ($this->approval)($request);
    }
}
