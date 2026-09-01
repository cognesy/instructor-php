<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Execution;

use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Execution\CanObserveTellRun;
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
    public function start(TellRequest $request): CanObserveTellRun {
        return $this->runtime->create()->start($request);
    }
}
