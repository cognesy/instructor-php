<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Contracts\CanCreateTellRuntime;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Generator;

final readonly class DefaultTellRunner implements CanRunTell
{
    public function __construct(private CanCreateTellRuntime $runtime) {}

    #[\Override]
    public function run(TellRequest $request): TellResult {
        return $this->runtime->create()->run($request);
    }

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    #[\Override]
    public function stream(TellRequest $request): Generator {
        return $this->runtime->create()->stream($request);
    }

    #[\Override]
    public function start(TellRequest $request): TellRun {
        return $this->runtime->create()->start($request);
    }
}
