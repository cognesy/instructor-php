<?php

namespace Cognesy\Instructor\Extras\Module\Task\Contracts;

use Cognesy\Instructor\Extras\Module\Task\Enums\TaskStatus;
use Cognesy\Instructor\Extras\Module\TaskData\Contracts\HasInputOutputData;

interface CanBeProcessed
{
    public function inputs() : ?array;

    public function outputs() : ?array;

    public function status() : TaskStatus;

    public function onSuccess(callable $callback) : static;

    public function onFailure(callable $callback) : static;
}
