<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Shell\TellShellJobApproval;
use Cognesy\Tell\Shell\TellShellJobRequest;

interface CanApproveTellShellJobs
{
    public function approve(TellShellJobRequest $request): TellShellJobApproval;
}
