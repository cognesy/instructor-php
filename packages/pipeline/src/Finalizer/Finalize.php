<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Finalizer;

use Closure;
use Cognesy\Pipeline\Contracts\CanFinalizeProcessing;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\Call;
use RuntimeException;

readonly class Finalize implements CanFinalizeProcessing {
    private function __construct(
        private CanProcessState $processor,
        private Closure $onFailure,
    ) {}

    public static function withValue(callable $finalize): self {
        return new self(
            Call::withValue($finalize),
            fn(ProcessingState $state) => throw new RuntimeException(
                'Finalization failed: ' . $state->exception()->getMessage()
            )
        );
    }

    public static function passThrough(): self {
        return new self(
            Call::withValue(fn($data) => $data),
            fn(ProcessingState $state) => throw $state->exception()
        );
    }

    public static function withResult(callable $finalize): self {
        return new self(
            Call::withResult($finalize),
            fn(ProcessingState $state) => throw new RuntimeException(
                'Finalization failed: ' . $state->exception()->getMessage()
            )
        );
    }

    public static function withState(callable $finalize): self {
        return new self(
            Call::withState($finalize),
            fn(ProcessingState $state) => throw new RuntimeException(
                'Finalization failed: ' . $state->exception()->getMessage()
            )
        );
    }

    public function onFailure(callable $onFailure): self {
        return new self($this->processor, $onFailure);
    }

    public function finalize(ProcessingState $state): mixed {
        $output = $this->processor->process($state);
        return match(true) {
            $output->isFailure() => ($this->onFailure)($output),
            default => $output->value(),
        };
    }
}
