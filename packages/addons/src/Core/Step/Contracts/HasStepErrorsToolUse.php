<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Step\Contracts;

use Throwable;

interface HasStepErrorsToolUse
{
    public function hasErrors(): bool;

    /** @return Throwable[] */
    public function errors(): array;

    public function errorsAsString(): string;
}