<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Generator;

final readonly class DefaultTellRunner implements CanRunTell
{
    public function __construct(private TellRuntime $runtime) {}

    public function run(TellRequest $request): TellResult {
        return $this->runtime->run($request);
    }

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    public function stream(TellRequest $request): Generator {
        return $this->runtime->stream($request);
    }

    public function start(TellRequest $request): TellRun {
        return $this->runtime->start($request);
    }
}
