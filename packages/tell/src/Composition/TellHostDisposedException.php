<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use LogicException;

final class TellHostDisposedException extends LogicException
{
    public function __construct() {
        parent::__construct('Tell host has been disposed.');
    }
}
