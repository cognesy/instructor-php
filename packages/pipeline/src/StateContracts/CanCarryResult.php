<?php declare(strict_types=1);

namespace Cognesy\Pipeline\StateContracts;

use Cognesy\Utils\Result\Result;
use Throwable;

interface CanCarryResult
{
    public function withResult(Result $result): static;

    public function result(): Result;
    public function value(): mixed;
    public function valueOr(mixed $default): mixed;
    public function exception(): Throwable;
    public function exceptionOr(mixed $default): mixed;

    public function failWith(string|Throwable $cause): static;

    public function isSuccess(): bool;
    public function isFailure(): bool;
}