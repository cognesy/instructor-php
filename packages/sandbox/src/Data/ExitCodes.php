<?php

declare(strict_types=1);

namespace Cognesy\Sandbox\Data;

final class ExitCodes
{
    public const COMMAND_NOT_FOUND = 127;

    public const TIMEOUT = 124; // common convention (like GNU timeout)
}
