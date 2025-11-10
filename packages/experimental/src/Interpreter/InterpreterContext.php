<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter;

/**
 * InterpreterContext
 * - The "world" in which evaluation runs
 * - Environment, store, log, budget, etc.
 */
final readonly class InterpreterContext
{
    public function __construct(
        public array $environment = [],
        public array $log = [],
        // ...heap, stack, fuel, whatever else you need
    ) {}

    public static function initial() : self {
        return new self();
    }

    // MUTATORS //////////////////////////////////////////

    public function withEnvironment(array $environment) : self {
        return $this->with(environment: $environment);
    }

    public function withLog(array $log) : self {
        return $this->with(log: $log);
    }

    // INTERNAL //////////////////////////////////////////

    public function with(
        ?array $environment = null,
        ?array $log = null,
    ) : self {
        return new self(
          environment: $environment ?? $this->environment,
          log: $log ?? $this->log,
        );
    }
}
