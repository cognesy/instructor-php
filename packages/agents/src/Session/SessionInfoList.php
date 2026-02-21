<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, AgentSessionInfo>
 */
final readonly class SessionInfoList implements Countable, IteratorAggregate
{
    /** @var list<AgentSessionInfo> */
    private array $items;

    // CONSTRUCTORS ////////////////////////////////////////////////

    public function __construct(AgentSessionInfo ...$items) {
        $this->items = $items;
    }

    public static function empty(): self {
        return new self();
    }

    public static function fromArray(array $data): self {
        $items = array_map(
            static fn(array $item): AgentSessionInfo => AgentSessionInfo::fromArray($item),
            $data,
        );
        return new self(...$items);
    }

    // ACCESSORS ///////////////////////////////////////////////////

    /** @return list<AgentSessionInfo> */
    public function all(): array {
        return $this->items;
    }

    #[\Override]
    public function count(): int {
        return count($this->items);
    }

    public function isEmpty(): bool {
        return $this->items === [];
    }

    public function first(): ?AgentSessionInfo {
        return $this->items[0] ?? null;
    }

    // ITERATORS ///////////////////////////////////////////////////

    /** @return Traversable<int, AgentSessionInfo> */
    #[\Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }

    // MUTATORS ////////////////////////////////////////////////////

    public function filterByStatus(SessionStatus $status): self {
        $filtered = array_filter(
            $this->items,
            static fn(AgentSessionInfo $info): bool => $info->status() === $status,
        );
        return new self(...array_values($filtered));
    }

    public function filterByAgentName(string $agentName): self {
        $filtered = array_filter(
            $this->items,
            static fn(AgentSessionInfo $info): bool => $info->agentName() === $agentName,
        );
        return new self(...array_values($filtered));
    }

    // SERIALIZATION ///////////////////////////////////////////////

    public function toArray(): array {
        return array_map(
            static fn(AgentSessionInfo $info): array => $info->toArray(),
            $this->items,
        );
    }
}
