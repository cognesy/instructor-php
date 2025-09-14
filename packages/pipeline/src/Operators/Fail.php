<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\TagMap\Tags\ErrorTag;
use RuntimeException;
use Throwable;

readonly final class Fail implements CanProcessState {
    private function __construct(
        private Throwable $e,
    ) {}

    public static function with(Throwable|string $e) : self {
        return new self(match(true) {
            is_string($e) => new RuntimeException($e),
            $e instanceof Throwable => $e,
        });
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        if ($state->isFailure()) {
            return $next ? $next($state) : $state;
        }

        $failedState = $state
            ->withResult(Result::failure($this->e))
            ->addTags(ErrorTag::fromException($this->e));

        return $next ? $next($failedState) : $failedState;
    }
}