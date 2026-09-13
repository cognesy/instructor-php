<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Override;
use Symfony\Component\Console\Command\Command;
use Traversable;

/** @implements IteratorAggregate<int, Command> */
final readonly class TellCommands implements Countable, IteratorAggregate
{
    /** @var list<Command> */
    private array $commands;

    public function __construct(Command ...$commands) {
        $names = [];
        foreach ($commands as $command) {
            $name = $command->getName();
            if ($name === null || preg_match('/^[a-z][a-z0-9:_-]*$/', $name) !== 1) {
                throw new InvalidArgumentException('Tell commands require a valid name.');
            }
            if (isset($names[$name])) {
                throw new InvalidArgumentException("Duplicate Tell command name: {$name}.");
            }
            $names[$name] = true;
        }
        $this->commands = array_values($commands);
    }

    public static function empty(): self {
        return new self();
    }

    public function with(Command ...$commands): self {
        return new self(...$this->commands, ...$commands);
    }

    public function merge(self $commands): self {
        return $this->with(...$commands->all());
    }

    /** @return list<Command> */
    public function all(): array {
        return $this->commands;
    }

    #[Override]
    public function count(): int {
        return count($this->commands);
    }

    /** @return Traversable<int, Command> */
    #[Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->commands);
    }
}
