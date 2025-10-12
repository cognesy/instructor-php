<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer;

use Cognesy\Utils\Stream\Stream;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Sinks\{ToArrayReducer};
use Cognesy\Utils\Transducer\Support\IteratorUtils;
use Cognesy\Utils\Transducer\Transducers\Compose;
use InvalidArgumentException;

final readonly class Transduce
{
    private array $transducers;
    private ?Reducer $sink;
    private ?iterable $source;

    public function __construct(
        array $transducers = [],
        ?Reducer $sink = null,
        ?iterable $source = null,
    ) {
        $this->transducers = $transducers;
        $this->sink = $sink ?? new ToArrayReducer();
        $this->source = $source;
    }

    public function through(Transducer ...$transducers): self {
        return $this->with(transducers: [...$this->transducers, ...$transducers]);
    }

    public function withSink(Reducer $sink) : self {
        return $this->with(sink: $sink);
    }

    public function withInput(Stream|iterable $input) : self {
        return $this->with(source: match(true) {
            $input instanceof Stream => $input->getIterator(),
            default => $input,
        });
    }

    // Streaming facade /////////////////////////////////////////////////////

    public function transduction(): Transduction {
        if ($this->source === null) {
            throw new InvalidArgumentException('Source is not set. Use applyTo($input) or withSource($source)->apply()');
        }
        return $this->makeTransduction();
    }

    public function apply() : mixed {
        return $this->transduction()->final();
    }

    public function applyTo(mixed $input): mixed {
        return $this->with(source: $input)->apply();
    }

    // INTERNAL //////////////////////////////////////////////////////////

    private function with(
        ?array $transducers = null,
        ?Reducer $sink = null,
        ?iterable $source = null,
    ): self {
        return new self(
            transducers: $transducers ?? $this->transducers,
            sink: $sink ?? $this->sink ?? new ToArrayReducer(),
            source: $source ?? $this->source,
        );
    }

    private function makeTransduction(): Transduction {
        $reducerStack = (new Compose($this->transducers))($this->sink);
        $iterator = IteratorUtils::toIterator($this->source);
        $initialAccumulator = $reducerStack->init();
        return new Transduction(
            reducerStack: $reducerStack,
            iterator: $iterator,
            accumulator: $initialAccumulator,
        );
    }
}
