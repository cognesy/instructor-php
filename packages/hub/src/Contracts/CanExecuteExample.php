<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;

interface CanExecuteExample
{
    public function execute(Example $example): ExecutionResult;

    public function setTracker(?CanTrackExecution $tracker): void;

    public function canExecute(Example $example): bool;
}
