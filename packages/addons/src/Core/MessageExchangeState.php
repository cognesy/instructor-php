<?php declare(strict_types=1);

namespace Cognesy\Addons\Core;

use Cognesy\Addons\Core\StateContracts\HasMessageStore;
use Cognesy\Addons\Core\StateContracts\HasMetadata;
use Cognesy\Addons\Core\StateContracts\HasStateInfo;
use Cognesy\Addons\Core\StateContracts\HasUsage;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

readonly class MessageExchangeState implements HasMetadata, HasMessageStore, HasUsage, HasStateInfo
{
    const DEFAULT_SECTION = 'messages';

    protected string $id;
    protected DateTimeImmutable $startedAt;
    protected DateTimeImmutable $updatedAt;

    protected Metadata $metadata;
    protected Usage $usage;
    protected MessageStore $store;

    public function __construct(
        Metadata|array|null $variables = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?string $id = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->metadata = match(true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();

        $this->id = $id ?? Uuid::uuid4();
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    // HasStateInfo ////////////////////////////////////////////////////////

    public function id(): string {
        return $this->id;
    }

    public function startedAt(): DateTimeImmutable {
        return $this->startedAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    // HasUsage ///////////////////////////////////////////////////////////

    public function usage(): Usage {
        return $this->usage;
    }

    public function withUsage(Usage $usage): static {
        return new static(
            variables: $this->metadata,
            usage: $usage,
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withAccumulatedUsage(Usage $usage): static {
        $newUsage = $this->usage->clone();
        $newUsage->accumulate($usage);
        return $this->withUsage($usage);
    }

    // HasMessageStore ////////////////////////////////////////////////////

    public function messages(): Messages {
        return $this->store->section(self::DEFAULT_SECTION)->get()?->messages()
            ?? Messages::empty();
    }

    public function store() : MessageStore {
        return $this->store;
    }

    public function withStore(MessageStore $store): static {
        return new static(
            variables: $this->metadata,
            usage: $this->usage,
            store: $store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    // HasMetadata ////////////////////////////////////////////////////////

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function withMetadata(string $name, mixed $value): static {
        return new static(
            variables: $this->metadata->withKeyValue($name, $value),
            usage: $this->usage,
            store: $this->store,
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'startedAt' => $this->startedAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'metadata' => $this->metadata->toArray(),
            'usage' => $this->usage->toArray(),
            'messageStore' => $this->store->toArray(),
        ];
    }
}