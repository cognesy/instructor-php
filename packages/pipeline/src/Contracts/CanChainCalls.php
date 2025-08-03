<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\ProcessingState;

interface CanChainCalls {
    public function processor(): CanProcessState;
    public function process(ProcessingState $input, callable $next): ProcessingState;
}