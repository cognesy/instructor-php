<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Traits;

use Cognesy\Utils\Result\Result;
use Cognesy\Utils\TagMap\Tags\ErrorTag;
use RuntimeException;
use Throwable;

trait HandlesResult
{
    protected readonly Result $result;

    public function withResult(Result $result): static {
        return new self($result, $this->tags);
    }

    public function failWith(string|Throwable $cause): static {
        $message = $cause instanceof Throwable ? $cause->getMessage() : $cause;
        $exception = match (true) {
            is_string($cause) => new RuntimeException($cause),
            $cause instanceof Throwable => $cause,
        };
        return $this
            ->withResult(Result::failure($exception))
            ->addTags(new ErrorTag(error: $message));
    }

    public function result(): Result {
        return $this->result;
    }

    public function value(): mixed {
        if ($this->result->isFailure()) {
            throw new RuntimeException('Cannot unwrap value from a failed result');
        }
        return $this->result->unwrap();
    }

    public function valueOr(mixed $default): mixed {
        return $this->result->valueOr($default);
    }

    public function isSuccess(): bool {
        return $this->result->isSuccess();
    }

    public function isFailure(): bool {
        return $this->result->isFailure();
    }

    public function exception(): Throwable {
        if ($this->result->isSuccess()) {
            throw new RuntimeException('Cannot get exception from a successful result');
        }
        return $this->result->exception();
    }

    public function exceptionOr(mixed $default): mixed {
        return $this->result->exceptionOr($default);
    }
}