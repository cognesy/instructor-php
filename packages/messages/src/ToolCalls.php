<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Utils\Json\JsonDecoder;
use InvalidArgumentException;

final readonly class ToolCalls
{
    /** @var ToolCall[] */
    private array $toolCalls;

    public function __construct(ToolCall ...$toolCalls)
    {
        $this->toolCalls = $toolCalls;
    }

    // CONSTRUCTORS ////////////////////////////////////////////////

    public static function empty(): self
    {
        return new self();
    }

    public static function fromArray(array $toolCalls): self
    {
        $list = [];
        foreach ($toolCalls as $key => $toolCall) {
            $toolCall = match (true) {
                $toolCall instanceof ToolCall => $toolCall,
                is_array($toolCall) => ToolCall::fromArray($toolCall),
                is_string($toolCall) => new ToolCall($key, self::makeArgs($toolCall)),
                default => throw new InvalidArgumentException('Cannot create ToolCall from provided data: ' . print_r($toolCall, true)),
            };
            $list[] = $toolCall;
        }

        return new self(...$list);
    }

    /**
     * @param callable(mixed): ?ToolCall $mapper
     */
    public static function fromMapper(array $items, callable $mapper): self
    {
        $list = [];
        foreach ($items as $item) {
            $toolCall = $mapper($item);
            if ($toolCall instanceof ToolCall) {
                $list[] = $toolCall;
            }
        }

        return new self(...$list);
    }

    // MUTATORS /////////////////////////////////////////////////////

    public function withAddedToolCall(string $name, array $args = []): self
    {
        return new self(...[...$this->toolCalls, new ToolCall(name: $name, arguments: $args)]);
    }

    public function withLastToolCallUpdated(string $name, string $jsonString): self
    {
        $argsArray = self::makeArgs($jsonString);
        if (empty($this->toolCalls)) {
            return $this->withAddedToolCall($name, $argsArray);
        }

        $updatedCalls = $this->toolCalls;
        $lastIndex = count($updatedCalls) - 1;
        $lastCall = $updatedCalls[$lastIndex];

        $updatedCall = $lastCall->withArguments($argsArray);
        if (empty($lastCall->name())) {
            $updatedCall = $updatedCall->withName($name);
        }

        $updatedCalls[$lastIndex] = $updatedCall;

        return new self(...$updatedCalls);
    }

    // ACCESSORS ////////////////////////////////////////////////////

    /** @return ToolCall[] */
    public function all(): array
    {
        return $this->toolCalls;
    }

    public function first(): ?ToolCall
    {
        return $this->toolCalls[0] ?? null;
    }

    public function last(): ?ToolCall
    {
        $index = array_key_last($this->toolCalls);

        return match (true) {
            $index === null => null,
            default => $this->toolCalls[$index],
        };
    }

    public function count(): int
    {
        return count($this->toolCalls);
    }

    public function isEmpty(): bool
    {
        return $this->toolCalls === [];
    }

    public function hasAny(): bool
    {
        return !empty($this->toolCalls);
    }

    public function hasNone(): bool
    {
        return empty($this->toolCalls);
    }

    public function hasSingle(): bool
    {
        return count($this->toolCalls) === 1;
    }

    public function hasMany(): bool
    {
        return count($this->toolCalls) > 1;
    }

    /** @return iterable<ToolCall> */
    public function each(): iterable
    {
        foreach ($this->toolCalls as $toolCall) {
            yield $toolCall;
        }
    }

    // TRANSFORMERS ////////////////////////////////////////////////

    /** @param callable(ToolCall):mixed $callback */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->toolCalls);
    }

    /** @param callable(ToolCall):bool $callback */
    public function filter(callable $callback): self
    {
        return new self(...array_values(array_filter($this->toolCalls, $callback)));
    }

    /** @param callable(mixed, ToolCall):mixed $callback */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->toolCalls, $callback, $initial);
    }

    public function toArray(): array
    {
        return array_map(
            fn (ToolCall $toolCall) => $toolCall->toArray(),
            $this->toolCalls,
        );
    }

    public function toString(): string
    {
        return implode(' | ', array_map(
            fn (ToolCall $toolCall) => $toolCall->toString(),
            $this->toolCalls,
        ));
    }

    // INTERNAL ////////////////////////////////////////////////////

    private static function makeArgs(string|array $args): array
    {
        if (is_array($args)) {
            return $args;
        }

        return empty($args) ? [] : JsonDecoder::decodeToArray($args);
    }
}
