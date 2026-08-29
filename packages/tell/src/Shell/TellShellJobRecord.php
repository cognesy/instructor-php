<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use CordisPhp\Runtime\Fiber;

/** @internal */
final class TellShellJobRecord
{
    public bool $terminalObserved = false;

    public function __construct(
        public readonly TellShellJobProcess $process,
        public readonly Fiber $fiber,
    ) {}
}
