<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Closure;
use InvalidArgumentException;

/** One immutable, factory-backed module admitted before host boot. */
final readonly class TellModuleDefinition
{
    /** @var Closure(mixed ...): object */
    private Closure $factory;

    /**
     * @param  list<class-string>  $provides
     * @param  list<class-string>  $requires
     * @param  list<class-string>  $optional
     * @param  callable(mixed ...): object  $factory
     */
    public function __construct(
        public string $id,
        public array $provides,
        callable $factory,
        public array $requires = [],
        public array $optional = [],
        public string $description = '',
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]*$/', $this->id) !== 1) {
            throw new InvalidArgumentException("Invalid Tell module id: {$this->id}");
        }
        if ($this->provides === []) {
            throw new InvalidArgumentException("Tell module {$this->id} must advertise at least one capability.");
        }
        $this->factory = Closure::fromCallable($factory);
    }

    /** @param list<object|null> $dependencies */
    public function create(array $dependencies): object {
        return ($this->factory)(...$dependencies);
    }

    /** @return array{id: string, description: string, provides: list<class-string>, requires: list<class-string>, optional: list<class-string>} */
    public function describe(): array {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'provides' => $this->provides,
            'requires' => $this->requires,
            'optional' => $this->optional,
        ];
    }
}
