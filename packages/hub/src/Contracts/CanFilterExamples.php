<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

use Cognesy\InstructorHub\Data\ExampleExecutionStatus;

interface CanFilterExamples
{
    public function shouldExecute(ExampleExecutionStatus $status): bool;

    public function getDescription(): string;
}
