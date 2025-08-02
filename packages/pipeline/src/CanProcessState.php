<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

interface CanProcessState {
    public function execute(ProcessingState $state): ProcessingState;
}
