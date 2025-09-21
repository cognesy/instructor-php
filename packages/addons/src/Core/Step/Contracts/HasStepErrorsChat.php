<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Step\Contracts;

use Throwable;

/**
 * @template TError of Throwable
 */
interface HasStepErrorsChat
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
