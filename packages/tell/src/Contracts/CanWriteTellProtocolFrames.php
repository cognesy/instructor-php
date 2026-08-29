<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellResult;

/** Per-run protocol output boundary; implementations own framing and size limits. */
interface CanWriteTellProtocolFrames
{
    public function identify(string $id): void;

    public function progress(TellProgress $progress): void;

    public function success(TellResult $result): void;

    public function error(
        string $code,
        string $message,
        ?TellResult $result = null,
        ?string $reason = null,
    ): void;

    public function cancelled(TellResult $result): void;

    public function hasTerminalFrame(): bool;
}
