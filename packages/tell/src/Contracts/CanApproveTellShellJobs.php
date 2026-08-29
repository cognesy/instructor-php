<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellShellJobApproval;
use Cognesy\Tell\Data\TellShellJobRequest;

interface CanApproveTellShellJobs
{
    public function approve(TellShellJobRequest $request): TellShellJobApproval;
}
