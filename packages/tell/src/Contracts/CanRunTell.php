<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\TellProgress;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\TellResult;
use Generator;

interface CanRunTell
{
    public function run(TellRequest $request): TellResult;

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    public function stream(TellRequest $request): Generator;
}
