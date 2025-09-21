<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StepContracts;

use Throwable;

/**
 * @template TError of Throwable
 */
interface HasStepErrors
{
    /**
     * Determine whether the step captured any errors.
     */
    public function hasErrors(): bool;

    /**
     * @return iterable<TError>
     */
    public function errors(): iterable;

    public function errorsAsString(): string;
}
