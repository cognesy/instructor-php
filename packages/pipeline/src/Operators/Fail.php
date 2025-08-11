<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

readonly final class Fail implements CanControlStateProcessing {
    private function __construct(
        private Throwable $e,
    ) {}

    public static function with(Throwable|string $e) : self {
        return new self(match(true) {
            is_string($e) => new RuntimeException($e),
            $e instanceof Throwable => $e,
        });
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        if ($state->isFailure()) {
            return $next ? $next($state) : $state;
        }

        $failedState = $state
            ->withResult(Result::failure($this->e))
            ->withTags(ErrorTag::fromException($this->e));

        return $next ? $next($failedState) : $failedState;
    }
}