<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource\Exception;

use RuntimeException;

final class TellResourceHostDisposedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tell resource host has been disposed.');
    }
}
