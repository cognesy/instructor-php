<?php declare(strict_types=1);

namespace Cognesy\Addons\Core;

use Cognesy\Addons\Core\StateContracts\HasMessageStore;
use Cognesy\Addons\Core\StateContracts\HasMetadata;
use Cognesy\Addons\Core\StateContracts\HasStateInfo;
use Cognesy\Addons\Core\StateContracts\HasUsage;
use Cognesy\Addons\Core\StateInfo;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;

readonly class MessageExchangeState implements HasMetadata, HasMessageStore, HasUsage, HasStateInfo
{
    const DEFAULT_SECTION = 'messages';

    protected StateInfo $stateInfo;
    protected Metadata $metadata;
    protected Usage $usage;
    protected MessageStore $store;

    public function __construct(
        Metadata|array|null $variables = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
    ) {
        $this->stateInfo = $stateInfo ?? StateInfo::new();
        $this->metadata = match(true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
    }

    // HasStateInfo ////////////////////////////////////////////////////////

    public function stateInfo(): StateInfo {
        return $this->stateInfo;
    }

    public function withStateInfo(StateInfo $stateInfo): static {
        return $this->with(stateInfo: $stateInfo);
    }

    public function id(): string { return $this->stateInfo->id(); }
    public function startedAt(): DateTimeImmutable { return $this->stateInfo->startedAt(); }
    public function updatedAt(): DateTimeImmutable { return $this->stateInfo->updatedAt(); }

    // HasUsage ///////////////////////////////////////////////////////////

    public function usage(): Usage { return $this->usage; }
    public function withUsage(Usage $usage): static { return $this->with(usage: $usage); }

    public function withAccumulatedUsage(Usage $usage): static {
        $newUsage = $this->usage->clone();
        $newUsage->accumulate($usage);
        return $this->withUsage($newUsage);
    }

    // HasMessageStore ////////////////////////////////////////////////////

    public function messages(): Messages {
        return $this->store->section(self::DEFAULT_SECTION)->get()?->messages()
            ?? Messages::empty();
    }

    public function store() : MessageStore { return $this->store; }
    public function withStore(MessageStore $store): static { return $this->with(store: $store); }

    // HasMetadata ////////////////////////////////////////////////////////

    public function metadata(): Metadata { return $this->metadata; }

    public function withMetadata(string $name, mixed $value): static {
        return $this->with(variables: $this->metadata->withKeyValue($name, $value));
    }

    // SERIALIZATION //////////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'metadata' => $this->metadata->toArray(),
            'usage' => $this->usage->toArray(),
            'messageStore' => $this->store->toArray(),
            'stateInfo' => $this->stateInfo->toArray(),
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            variables: isset($data['metadata']) ? Metadata::fromArray($data['metadata']) : null,
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : null,
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
        );
    }

    // WITH ///////////////////////////////////////////////////////////////

    public function with(
        ?Metadata $variables = null,
        ?Usage $usage = null,
        ?MessageStore $store = null,
        ?StateInfo $stateInfo = null,
    ): static {
        return new static(
            variables: $variables ?? $this->metadata,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
        );
    }
}