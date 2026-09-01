<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\ShellJob\Process\Exception;

use RuntimeException;

final class TellShellJobNotFoundException extends RuntimeException
{
    public function __construct(string $jobId) {
        parent::__construct("Tell shell job {$jobId} does not exist in this host.");
    }
}
