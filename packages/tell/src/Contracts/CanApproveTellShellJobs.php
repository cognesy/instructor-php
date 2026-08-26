<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\TellShellJobApproval;
use Cognesy\Tell\TellShellJobRequest;

interface CanApproveTellShellJobs
{
    public function approve(TellShellJobRequest $request): TellShellJobApproval;
}
