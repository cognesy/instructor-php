<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile\ShellJob;

use RuntimeException;

final class TellShellJobHostDisposedException extends RuntimeException
{
    public function __construct() {
        parent::__construct('The Tell shell-job host has been disposed.');
    }
}
