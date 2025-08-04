<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

readonly class Fail implements CanProcessState {
    private function __construct(
        private Throwable $e,
    ) {}

    public static function with(Throwable|string $e) : self {
        return new self(match(true) {
            is_string($e) => new RuntimeException($e),
            $e instanceof Throwable => $e,
        });
    }

    public function process(ProcessingState $state): ProcessingState {
        if ($state->isFailure()) {
            return $state;
        }
        return $state
            ->withResult(Result::failure($this->e))
            ->withTags(ErrorTag::fromException($this->e));
    }
}