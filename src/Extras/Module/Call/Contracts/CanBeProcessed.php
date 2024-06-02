<?php

namespace Cognesy\Instructor\Extras\Module\Call\Contracts;

use Cognesy\Instructor\Extras\Module\Call\Enums\CallStatus;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;

interface CanBeProcessed
{
    public function inputs() : ?array;

    public function outputs() : ?array;

    public function status() : CallStatus;

    public function onSuccess(callable $callback) : static;

    public function onFailure(callable $callback) : static;
}
