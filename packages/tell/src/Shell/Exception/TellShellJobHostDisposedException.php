<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell\Exception;

use RuntimeException;

final class TellShellJobHostDisposedException extends RuntimeException
{
    public function __construct() {
        parent::__construct('Tell shell-job host has been disposed.');
    }
}
