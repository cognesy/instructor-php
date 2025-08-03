<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\ProcessingState;

interface CanProcessState {
    public function process(ProcessingState $state): ProcessingState;
}
