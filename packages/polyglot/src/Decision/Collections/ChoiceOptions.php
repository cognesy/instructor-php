<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Collections;

use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use InvalidArgumentException;

final readonly class ChoiceOptions
{
    /** @var list<ChoiceOption> */
    private array $options;

    /** @var array<string, ChoiceOption> */
    private array $byId;

    private function __construct(ChoiceOption ...$options)
    {
        if ($options === []) {
            throw new InvalidArgumentException('Choice requires at least one option.');
        }
        $byId = [];
        foreach ($options as $option) {
            if (array_key_exists($option->id(), $byId)) {
                throw new InvalidArgumentException('Choice option IDs must be unique.');
            }
            $byId[$option->id()] = $option;
        }
        $this->options = array_values($options);
        $this->byId = $byId;
    }

    public static function of(ChoiceOption ...$options): self
    {
        return new self(...$options);
    }

    public static function fromArray(array $data): self
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException('Choice options must be a list.');
        }
        $options = array_map(
            static fn (mixed $option): ChoiceOption => is_array($option)
                ? ChoiceOption::fromArray($option)
                : throw new InvalidArgumentException('Each Choice option must be an object.'),
            $data,
        );

        return new self(...$options);
    }

    /** @return list<ChoiceOption> */
    public function all(): array
    {
        return $this->options;
    }

    public function count(): int
    {
        return count($this->options);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->byId);
    }

    public function option(string $id): ChoiceOption
    {
        return $this->byId[$id]
            ?? throw new InvalidArgumentException("Choice option '{$id}' does not exist.");
    }

    public function toArray(): array
    {
        return array_map(static fn (ChoiceOption $option): array => $option->toArray(), $this->options);
    }
}
