<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\ShellJob\Process\Exception;

use RuntimeException;

final class TellShellJobsDisposedException extends RuntimeException
{
    public function __construct() {
        parent::__construct('Tell shell-job host has been disposed.');
    }
}
