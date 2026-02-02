<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Context;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Utils\Metadata;

final readonly class AgentContext
{
    public const DEFAULT_SECTION = 'messages';
    public const BUFFER_SECTION = 'buffer';
    public const SUMMARY_SECTION = 'summary';
    public const EXECUTION_BUFFER_SECTION = 'execution_buffer';

    private MessageStore $store;
    private Metadata $metadata;
    private CachedInferenceContext $cache;

    public function __construct(
        ?MessageStore $store = null,
        Metadata|array|null $metadata = null,
        ?CachedInferenceContext $cache = null,
    ) {
        $this->store = $store ?? new MessageStore();
        $this->metadata = match (true) {
            $metadata === null => new Metadata(),
            $metadata instanceof Metadata => $metadata,
            is_array($metadata) => new Metadata($metadata),
            default => new Metadata(),
        };
        $this->cache = $cache ?? new CachedInferenceContext();
    }

    public function store(): MessageStore
    {
        return $this->store;
    }

    public function metadata(): Metadata
    {
        return $this->metadata;
    }

    public function cache(): CachedInferenceContext
    {
        return $this->cache;
    }

    public function messages(): Messages
    {
        return $this->store->section(self::DEFAULT_SECTION)->get()->messages();
    }

    public function messagesForInference(): Messages
    {
        $sectionNames = [
            self::SUMMARY_SECTION,
            self::BUFFER_SECTION,
            self::DEFAULT_SECTION,
            self::EXECUTION_BUFFER_SECTION,
        ];

        $resolved = [];
        foreach ($sectionNames as $sectionName) {
            $section = $this->store->sections()->get($sectionName);
            if ($section === null) {
                continue;
            }
            $resolved[] = $section;
        }

        if ($resolved === []) {
            return Messages::empty();
        }

        return (new Sections(...$resolved))->toMessages();
    }

    // MUTATORS /////////////////////////////////////////////////

    public function with(
        ?MessageStore $store = null,
        ?Metadata $metadata = null,
        ?CachedInferenceContext $cache = null,
    ): self {
        return new self(
            store: $store ?? $this->store,
            metadata: $metadata ?? $this->metadata,
            cache: $cache ?? $this->cache,
        );
    }

    public function withMessageStore(MessageStore $store): self
    {
        return $this->with(store: $store);
    }

    public function withMessages(Messages $messages): self
    {
        return $this->with(
            store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages),
        );
    }

    public function withMetadataKey(string $name, mixed $value): self
    {
        return $this->with(metadata: $this->metadata->withKeyValue($name, $value));
    }

    public function withMetadata(Metadata $metadata): self
    {
        return $this->with(metadata: $metadata);
    }

    public function withCache(CachedInferenceContext $cache): self
    {
        return $this->with(cache: $cache);
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array
    {
        return [
            'metadata' => $this->metadata->toArray(),
            'cachedContext' => $this->cache->toArray(),
            'messageStore' => $this->store->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            store: self::parseMessageStore($data),
            metadata: self::parseMetadata($data),
            cache: self::parseCachedContext($data),
        );
    }

    // PARSING HELPERS //////////////////////////////////////////

    private static function parseMetadata(array $data): Metadata
    {
        $value = $data['metadata'] ?? null;
        return is_array($value) ? Metadata::fromArray($value) : new Metadata();
    }

    private static function parseCachedContext(array $data): CachedInferenceContext
    {
        $value = $data['cachedContext'] ?? null;
        return is_array($value) ? CachedInferenceContext::fromArray($value) : new CachedInferenceContext();
    }

    private static function parseMessageStore(array $data): MessageStore
    {
        $value = $data['messageStore'] ?? null;
        return is_array($value) ? MessageStore::fromArray($value) : new MessageStore();
    }
}
