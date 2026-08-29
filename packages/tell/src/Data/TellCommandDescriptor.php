<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Closure;
use InvalidArgumentException;

/** Framework-neutral descriptor whose factory may create a shell adapter command. */
final readonly class TellCommandDescriptor
{
    /** @var Closure(): object */
    private Closure $factory;

    /**
     * @param  callable(): object  $factory
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $name,
        callable $factory,
        public string $description = '',
        public array $aliases = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9:_-]*$/', $this->name) !== 1) {
            throw new InvalidArgumentException("Invalid Tell command name: {$this->name}");
        }
        foreach ($this->aliases as $alias) {
            if (preg_match('/^[a-z][a-z0-9:_-]*$/', $alias) !== 1) {
                throw new InvalidArgumentException("Invalid Tell command alias: {$alias}");
            }
        }
        $this->factory = Closure::fromCallable($factory);
    }

    public function create(): object {
        return ($this->factory)();
    }
}
