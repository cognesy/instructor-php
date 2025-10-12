<?php declare(strict_types=1);

namespace Cognesy\Stream;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Stream;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Sinks\{ToArrayReducer};
use Cognesy\Stream\Support\BufferedTransformationIterator;
use Cognesy\Stream\Support\IteratorUtils;
use Cognesy\Stream\Support\TransformationIterator;
use Cognesy\Stream\Transducers\Compose;
use InvalidArgumentException;
use Iterator;

final readonly class Transformation implements Transducer
{
    /** @var array<Transducer> */
    private array $transducers;
    private ?Reducer $sink;
    /** @var iterable<mixed>|null */
    private ?iterable $source;

    public function __construct(
        array $transducers = [],
        ?Reducer $sink = null,
        ?iterable $source = null,
    ) {
        $this->transducers = $transducers;
        $this->sink = $sink;
        $this->source = $source;
    }

    public static function define(Transducer ...$transducers): self {
        return new self(transducers: $transducers);
    }

    // MUTATORS ////////////////////////////////////////////////////////

    public function through(Transducer ...$transducers): self {
        return $this->with(transducers: [...$this->transducers, ...$transducers]);
    }

    public function before(Transformation $transformation): self {
        $result = $transformation->through(...$this->transducers);
        if ($this->sink !== null) {
            $result = $result->withSink($this->sink);
        }
        if ($this->source !== null) {
            $result = $result->withInput($this->source);
        }
        return $result;
    }

    public function after(Transformation $transformation): self {
        $result = $this->with(transducers: [...$this->transducers, ...$transformation->transducers]);
        if ($transformation->sink !== null) {
            $result = $result->withSink($transformation->sink);
        }
        if ($transformation->source !== null) {
            $result = $result->withInput($transformation->source);
        }
        return $result;
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

    // ACCESSORS ///////////////////////////////////////////////////////

    public function sink(): ?Reducer {
        return $this->sink;
    }

    /**
     * @return iterable<mixed>|null
     */
    public function source(): ?iterable {
        return $this->source;
    }

    // EXECUTION ///////////////////////////////////////////////////////

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return (new Compose($this->transducers))($reducer);
    }

    public function execute() : mixed {
        return $this->execution()->completed();
    }

    public function executeOn(Stream|iterable $input): mixed {
        return $this->withInput($input)->execute();
    }

    public function execution(): TransformationExecution {
        if ($this->source === null) {
            throw new InvalidArgumentException('Source is not set. Use applyTo($input) or withInput($input)->apply()');
        }
        return $this->makeTransduction();
    }

    /**
     * Returns an iterator that yields intermediate accumulator values.
     * Enables foreach usage for step-by-step processing.
     *
     * @param bool $buffered If true, returns buffered iterator with rewind support
     * @return Iterator
     */
    public function iterator(bool $buffered = false): Iterator {
        $execution = $this->execution();
        return match(true) {
            $buffered => new BufferedTransformationIterator($execution),
            default => new TransformationIterator($execution),
        };
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

    private function makeTransduction(): TransformationExecution {
        if ($this->source === null) {
            throw new InvalidArgumentException('Source is not set');
        }
        $reducerStack = $this->makeReducerStack();
        $initialAccumulator = $reducerStack->init();
        return new TransformationExecution(
            reducerStack: $reducerStack,
            iterator: IteratorUtils::toIterator($this->source),
            accumulator: $initialAccumulator,
        );
    }

    private function makeReducerStack(): Reducer {
        return match(true) {
            ($this->sink !== null) => ($this)($this->sink),
            default => ($this)(new ToArrayReducer()),
        };
    }
}