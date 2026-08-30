<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

/** @internal */
final class TellShellJobRecord
{
    public bool $terminalObserved = false;

    public function __construct(public readonly TellShellJobProcess $process) {}
}
