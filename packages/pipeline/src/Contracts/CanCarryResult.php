<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Utils\Result\Result;
use Throwable;

interface CanCarryResult
{
    public function withResult(Result $result): static;
    public function failWith(string|Throwable $cause): static;
    public function result(): Result;
    public function value(): mixed;
    public function valueOr(mixed $default): mixed;
    public function isSuccess(): bool;
    public function isFailure(): bool;
    public function exception(): Throwable;
    public function exceptionOr(mixed $default): mixed;
}